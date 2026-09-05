<?php

namespace App\Services\Enrichment;

use App\Models\HadithImportJob;
use Illuminate\Support\Facades\Storage;
use JsonMachine\Items;
use RuntimeException;
use Throwable;

class HadithImportChunkService
{
    public function prepare(HadithImportJob $job, ?int $chunkSize = null): array
    {
        if ($manifest = $this->manifest($job)) {
            return $manifest;
        }

        $disk = Storage::disk('local');
        if (! $job->original_file_path || ! $disk->exists($job->original_file_path)) {
            throw new RuntimeException('Uploaded JSON file is missing from local storage');
        }

        $chunkSize ??= (int) config('dorar.enrichment_chunk_size', 25);
        $directory = 'enrichment_imports/'.$job->id;
        $absoluteDirectory = $disk->path($directory);
        if (! is_dir($absoluteDirectory) && ! mkdir($absoluteDirectory, 0775, true) && ! is_dir($absoluteDirectory)) {
            throw new RuntimeException('Unable to create the import chunk directory');
        }

        $chunks = [];
        $buffer = [];
        $count = 0;

        try {
            foreach (Items::fromFile($disk->path($job->original_file_path), ['pointer' => '/hadiths']) as $index => $hadith) {
                if (! is_array($hadith)) {
                    throw new RuntimeException("Hadith at index {$index} must be an object");
                }
                if (! isset($hadith['arabic']) || ! is_string($hadith['arabic']) || trim($hadith['arabic']) === '') {
                    throw new RuntimeException("Hadith at index {$index} requires a non-empty Arabic string");
                }
                $buffer[] = ['original_index' => $count, 'data' => $hadith];
                $count++;
                if (count($buffer) >= $chunkSize) {
                    $chunks[] = $this->writeChunk($absoluteDirectory, count($chunks), $buffer, $directory);
                    $buffer = [];
                }
            }
            if ($buffer !== []) {
                $chunks[] = $this->writeChunk($absoluteDirectory, count($chunks), $buffer, $directory);
            }
            if ($count === 0) {
                throw new RuntimeException('Invalid JSON: expected non-empty hadiths array');
            }

            $metadata = null;
            foreach (Items::fromFile($disk->path($job->original_file_path), ['pointer' => '/metadata']) as $key => $value) {
                $metadata[$key] = $value;
            }
            $wrapper = $metadata === null ? [] : ['metadata' => $metadata];
            $manifest = ['version' => 1, 'source_format' => 'hadith-json', 'total' => $count, 'wrapper' => $wrapper, 'chunks' => $chunks];
            $manifestPath = $directory.'/manifest.json';
            $this->atomicWrite($disk->path($manifestPath), json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $job->update(['manifest_path' => $manifestPath, 'chunk_directory' => $directory, 'original_wrapper' => $wrapper, 'total_hadiths' => $count, 'status' => $job->status === 'preparing' ? 'pending' : $job->status]);

            return $manifest;
        } catch (Throwable $e) {
            foreach (glob($absoluteDirectory.'/*.tmp-*') ?: [] as $temporary) {
                @unlink($temporary);
            }
            throw $e;
        }
    }

    public function manifest(HadithImportJob $job): ?array
    {
        if (! $job->manifest_path || ! Storage::disk('local')->exists($job->manifest_path)) {
            return null;
        }
        $manifest = json_decode(file_get_contents(Storage::disk('local')->path($job->manifest_path)), true, flags: JSON_THROW_ON_ERROR);

        return isset($manifest['total'], $manifest['chunks']) ? $manifest : null;
    }

    public function records(HadithImportJob $job): \Generator
    {
        foreach ($this->prepare($job)['chunks'] as $chunk) {
            $rows = json_decode(file_get_contents(Storage::disk('local')->path($chunk['path'])), true, flags: JSON_THROW_ON_ERROR);
            foreach ($rows as $row) {
                yield $row;
            }
        }
    }

    public function cleanup(HadithImportJob $job): bool
    {
        $expected = 'enrichment_imports/'.$job->id;
        if ($job->chunk_directory !== $expected || str_contains($expected, '..')) {
            return false;
        }

        return Storage::disk('local')->deleteDirectory($expected);
    }

    private function writeChunk(string $directory, int $number, array $rows, string $relativeDirectory): array
    {
        $name = sprintf('chunk-%06d.json', $number);
        $this->atomicWrite($directory.'/'.$name, json_encode($rows, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return ['number' => $number, 'start' => $rows[0]['original_index'], 'end' => $rows[array_key_last($rows)]['original_index'], 'count' => count($rows), 'path' => $relativeDirectory.'/'.$name];
    }

    private function atomicWrite(string $path, string $contents): void
    {
        $temporary = $path.'.tmp-'.bin2hex(random_bytes(6));
        if (file_put_contents($temporary, $contents, LOCK_EX) === false || ! rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('Unable to atomically write import data');
        }
    }
}
