<?php

namespace Tests\Unit\Enrichment;

use App\Services\Enrichment\HadithSearchQueryExtractor;
use PHPUnit\Framework\TestCase;

class HadithSearchQueryExtractorTest extends TestCase
{
    private HadithSearchQueryExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new HadithSearchQueryExtractor;
    }

    public function test_extracts_matn_from_real_first_bukhari_record(): void
    {
        $hadith = $this->fixtureHadith(0);

        $queries = $this->extractor->extract($hadith);

        $this->assertSame('إنما الأعمال بالنيات وإنما لكل امرئ ما نوى', $queries[0]);
        $this->assertSame('وإنما لكل امرئ ما نوى فمن كانت هجرته', $queries[1]);
        $this->assertStringNotContainsString('حدثنا', $queries[0]);
        $this->assertLessThanOrEqual(8, count(preg_split('/\s+/u', $queries[0]) ?: []));
    }

    public function test_extracts_matn_from_records_with_right_to_left_marks(): void
    {
        $samples = [
            [$this->fixtureHadith(1), 'بني الإسلام على خمس شهادة أن لا إله'],
            ['حَدَّثَنَا عَبْدُ اللَّهِ بْنُ مُحَمَّدٍ، عَنْ أَبِي هُرَيْرَةَ ـ رضى الله عنه ـ عَنِ النَّبِيِّ صلى الله عليه وسلم قَالَ ‏\n"‏ الإِيمَانُ بِضْعٌ وَسِتُّونَ شُعْبَةً، وَالْحَيَاءُ شُعْبَةٌ مِنَ الإِيمَانِ ‏"‏‏.‏', 'الإيمان بضع وستون شعبة والحياء شعبة من الإيمان'],
            ['حَدَّثَنَا آدَمُ بْنُ أَبِي إِيَاسٍ، عَنْ عَبْدِ اللَّهِ بْنِ عَمْرٍو ـ رضى الله عنهما ـ عَنِ النَّبِيِّ صلى الله عليه وسلم قَالَ ‏\n"‏ الْمُسْلِمُ مَنْ سَلِمَ الْمُسْلِمُونَ مِنْ لِسَانِهِ وَيَدِهِ، وَالْمُهَاجِرُ مَنْ هَجَرَ مَا نَهَى اللَّهُ عَنْهُ ‏"‏‏.‏', 'المسلم من سلم المسلمون من لسانه ويده والمهاجر'],
        ];

        foreach ($samples as [$hadith, $phrase]) {
            $query = $this->extractor->extract($hadith)[0];
            $this->assertSame($phrase, $query);
            $this->assertDoesNotMatchRegularExpression('/\p{Cf}/u', $query);
        }
    }

    public function test_short_text_is_still_searchable(): void
    {
        $this->assertSame(['حديث غير موجود'], $this->extractor->extract('حديث غير موجود'));
    }

    private function fixtureHadith(int $index): string
    {
        $path = dirname(__DIR__, 2).'/Fixtures/by_book_sample.json';
        $json = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        return $json['hadiths'][$index]['arabic'];
    }
}
