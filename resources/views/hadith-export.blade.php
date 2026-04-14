<!doctype html>
<html lang="ar" dir="rtl">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>عاااااااااا</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&family=IBM+Plex+Mono:wght@400;500&display=swap"
      rel="stylesheet"
    />
    <style>
      :root {
        --bg: #f4efe6;
        --ink: #1f2a1f;
        --muted: #495442;
        --panel: #f8f4ec;
        --accent: #2f6b3d;
        --accent-2: #175f5e;
        --danger: #9d2f2f;
        --border: #d3c7af;
      }

      * {
        box-sizing: border-box;
      }

      body {
        margin: 0;
        min-height: 100vh;
        color: var(--ink);
        font-family: "Cairo", sans-serif;
        background:
          radial-gradient(circle at 10% 10%, #d9e6d8 0%, transparent 32%),
          radial-gradient(circle at 90% 0%, #d8ece9 0%, transparent 35%),
          linear-gradient(145deg, #f4efe6 0%, #efe6d7 100%);
      }

      .wrap {
        max-width: 980px;
        margin: 0 auto;
        padding: 26px 16px 32px;
      }

      .hero {
        background: linear-gradient(140deg, #153a1d 0%, #2e5c37 55%, #175f5e 100%);
        color: #f8f6f0;
        border-radius: 20px;
        padding: 22px 20px;
        box-shadow: 0 16px 32px rgba(17, 46, 25, 0.2);
      }

      .hero h1 {
        margin: 0 0 8px;
        font-size: clamp(1.3rem, 4vw, 2rem);
      }

      .hero p {
        margin: 0;
        opacity: 0.92;
      }

      .links {
        margin-top: 14px;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
      }

      .links a {
        color: #ecfff0;
        text-decoration: none;
        padding: 7px 12px;
        border: 1px solid rgba(255, 255, 255, 0.35);
        border-radius: 999px;
        font-size: 0.92rem;
      }

      .panel {
        margin-top: 16px;
        background: var(--panel);
        border: 1px solid var(--border);
        border-radius: 18px;
        padding: 16px;
      }

      .grid {
        display: grid;
        gap: 12px;
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }

      .field {
        display: flex;
        flex-direction: column;
        gap: 6px;
      }

      .field.full {
        grid-column: 1 / -1;
      }

      label {
        font-weight: 700;
        color: #2d3a2a;
      }

      input,
      select {
        width: 100%;
        border: 1px solid #b9ad95;
        border-radius: 10px;
        padding: 10px 12px;
        font-size: 0.95rem;
        font-family: "Cairo", sans-serif;
        color: var(--ink);
        background: #fefcf7;
      }

      .inline {
        display: flex;
        align-items: center;
        gap: 8px;
      }

      .inline input[type="checkbox"] {
        width: auto;
      }

      .actions {
        margin-top: 12px;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
      }

      button {
        border: 0;
        border-radius: 10px;
        padding: 10px 14px;
        font-weight: 700;
        font-family: "Cairo", sans-serif;
        cursor: pointer;
      }

      .primary {
        background: var(--accent);
        color: #fff;
      }

      .secondary {
        background: #dbe4da;
        color: #223126;
      }

      .danger {
        background: #f4dddd;
        color: #5f1e1e;
      }

      button:disabled {
        opacity: 0.6;
        cursor: not-allowed;
      }

      .stats {
        margin-top: 12px;
        display: grid;
        gap: 10px;
        grid-template-columns: repeat(3, minmax(0, 1fr));
      }

      .stat {
        border: 1px dashed #b9ad95;
        border-radius: 10px;
        background: #fffdf9;
        padding: 10px;
      }

      .stat b {
        display: block;
        font-family: "IBM Plex Mono", monospace;
        font-size: 1.1rem;
        color: #1b3c20;
      }

      .status {
        margin-top: 12px;
        min-height: 24px;
        font-weight: 700;
      }

      .status.error {
        color: var(--danger);
      }

      .status.ok {
        color: #23592f;
      }

      .log {
        margin-top: 10px;
        border-radius: 10px;
        border: 1px solid #c2b69f;
        background: #1f261e;
        color: #d8e7d8;
        direction: ltr;
        text-align: left;
        font-size: 0.82rem;
        line-height: 1.45;
        font-family: "IBM Plex Mono", monospace;
        max-height: 260px;
        overflow: auto;
        padding: 10px;
        white-space: pre-wrap;
      }

      @media (max-width: 760px) {
        .grid {
          grid-template-columns: 1fr;
        }

        .stats {
          grid-template-columns: 1fr;
        }
      }
    </style>
  </head>
  <body>
    <main class="wrap">
      <section class="hero">
        <h1>أداة تصدير أحاديث كتاب إلى JSON</h1>
        <p>تسحب كل الصفحات من API الحالي، تدمج النتائج، وتزيل التكرار ثم تحفظ ملفًا واحدًا.</p>
        <div class="links">
          <a href="/docs" target="_blank" rel="noreferrer">/docs</a>
          <a href="/api-docs" target="_blank" rel="noreferrer">/api-docs</a>
          <a href="/v1/data/book" target="_blank" rel="noreferrer">/v1/data/book</a>
        </div>
      </section>

      <section class="panel">
        <div class="grid">
          <div class="field full">
            <label for="bookId">الكتاب</label>
            <select id="bookId">
              <option value="">تحميل قائمة الكتب...</option>
            </select>
          </div>

          <div class="field">
            <label for="searchValue">قيمة البحث `value`</label>
            <input id="searchValue" value="*" />
          </div>

          <div class="field">
            <label for="tabMode">وضع النتائج</label>
            <select id="tabMode">
              <option value="home">لغير المتخصص</option>
              <option value="specialist">للمتخصص</option>
              <option value="both">الاثنان معًا</option>
            </select>
          </div>

          <div class="field">
            <label for="delayMs">تأخير بين الصفحات (ms)</label>
            <input id="delayMs" type="number" min="0" step="100" value="250" />
          </div>

          <div class="field">
            <label class="inline" for="removeHtml">
              <input id="removeHtml" type="checkbox" checked />
              إزالة HTML من النص
            </label>
          </div>
        </div>

        <div class="actions">
          <button id="reloadBooks" class="secondary">إعادة تحميل الكتب</button>
          <button id="startExport" class="primary">بدء التصدير</button>
          <button id="stopExport" class="danger" disabled>إيقاف</button>
        </div>

        <div class="stats">
          <div class="stat">
            الصفحات المسحوبة
            <b id="pagesCount">0</b>
          </div>
          <div class="stat">
            السجلات الخام
            <b id="rawCount">0</b>
          </div>
          <div class="stat">
            بعد إزالة التكرار
            <b id="uniqueCount">0</b>
          </div>
        </div>

        <div id="status" class="status"></div>
        <pre id="log" class="log"></pre>
      </section>
    </main>

    <script>
      const els = {
        bookId: document.getElementById("bookId"),
        searchValue: document.getElementById("searchValue"),
        tabMode: document.getElementById("tabMode"),
        delayMs: document.getElementById("delayMs"),
        removeHtml: document.getElementById("removeHtml"),
        reloadBooks: document.getElementById("reloadBooks"),
        startExport: document.getElementById("startExport"),
        stopExport: document.getElementById("stopExport"),
        pagesCount: document.getElementById("pagesCount"),
        rawCount: document.getElementById("rawCount"),
        uniqueCount: document.getElementById("uniqueCount"),
        status: document.getElementById("status"),
        log: document.getElementById("log"),
      };

      let stopRequested = false;

      const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

      function setStatus(message, type = "") {
        els.status.textContent = message;
        els.status.className = `status ${type}`.trim();
      }

      function appendLog(message) {
        const now = new Date().toISOString().replace("T", " ").slice(0, 19);
        els.log.textContent += `[${now}] ${message}\n`;
        els.log.scrollTop = els.log.scrollHeight;
      }

      function setBusy(isBusy) {
        els.startExport.disabled = isBusy;
        els.reloadBooks.disabled = isBusy;
        els.stopExport.disabled = !isBusy;
      }

      function makeQueryString(params) {
        const query = new URLSearchParams();
        Object.entries(params).forEach(([key, value]) => {
          if (Array.isArray(value)) {
            value.forEach((v) => query.append(`${key}[]`, String(v)));
            return;
          }
          query.set(key, String(value));
        });

        return query.toString();
      }

      function dedupeHadith(items) {
        const seen = new Set();
        const result = [];
        for (const item of items) {
          const id = item?.hadithId?.toString()?.trim();
          const key = id && id !== "0" ? `id:${id}` : `txt:${(item?.hadith || "").slice(0, 200)}|bk:${item?.bookId || ""}`;
          if (seen.has(key)) {
            continue;
          }
          seen.add(key);
          result.push(item);
        }
        return result;
      }

      function downloadJson(filename, data) {
        const blob = new Blob([JSON.stringify(data, null, 2)], { type: "application/json;charset=utf-8" });
        const url = URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
      }

      async function loadBooks() {
        setStatus("تحميل قائمة الكتب...");
        appendLog("Loading books from /v1/data/book");

        const resp = await fetch("/v1/data/book");
        if (!resp.ok) {
          throw new Error(`Failed to load books: HTTP ${resp.status}`);
        }

        const payload = await resp.json();
        const books = Array.isArray(payload?.data) ? payload.data : [];

        els.bookId.innerHTML = "";
        for (const book of books) {
          const opt = document.createElement("option");
          opt.value = String(book.key ?? "");
          opt.textContent = `${book.value ?? "Unknown"} (${book.key ?? ""})`;
          els.bookId.appendChild(opt);
        }

        const bukhari = books.find((b) => String(b.value).trim() === "صحيح البخاري");
        if (bukhari?.key) {
          els.bookId.value = String(bukhari.key);
        }

        setStatus(`تم تحميل ${books.length} كتابًا.`, "ok");
        appendLog(`Books loaded: ${books.length}`);
      }

      async function fetchPaged(tabMode) {
        const all = [];
        const bookId = els.bookId.value.trim();
        const value = els.searchValue.value.trim() || "*";
        const delayMs = Math.max(0, Number(els.delayMs.value || 0));
        const removeHtml = els.removeHtml.checked;
        let page = 1;

        while (true) {
          if (stopRequested) {
            appendLog("Stop requested by user.");
            break;
          }

          const query = {
            value,
            page,
            removehtml: removeHtml,
            s: [bookId],
          };

          if (tabMode === "specialist") {
            query.specialist = true;
          }

          const url = `/v1/site/hadith/search?${makeQueryString(query)}`;
          appendLog(`GET ${url}`);

          const resp = await fetch(url);
          if (!resp.ok) {
            throw new Error(`HTTP ${resp.status} on page ${page}`);
          }

          const payload = await resp.json();
          if (payload?.status !== "success") {
            throw new Error(`API returned non-success on page ${page}`);
          }

          const pageData = Array.isArray(payload?.data) ? payload.data : [];
          all.push(...pageData);

          els.pagesCount.textContent = String(Number(els.pagesCount.textContent) + 1);
          els.rawCount.textContent = String(all.length);

          const hasNext = Boolean(payload?.metadata?.hasNextPage);
          appendLog(`page=${page} items=${pageData.length} hasNextPage=${hasNext}`);

          if (!hasNext) {
            break;
          }

          page += 1;
          if (delayMs > 0) {
            await sleep(delayMs);
          }
        }

        return all;
      }

      async function startExport() {
        const bookId = els.bookId.value.trim();
        if (!bookId) {
          setStatus("اختر كتابًا أولًا.", "error");
          return;
        }

        stopRequested = false;
        setBusy(true);
        els.log.textContent = "";
        els.pagesCount.textContent = "0";
        els.rawCount.textContent = "0";
        els.uniqueCount.textContent = "0";

        try {
          const bookText = els.bookId.options[els.bookId.selectedIndex]?.textContent || `book-${bookId}`;
          const tabMode = els.tabMode.value;

          setStatus("جاري سحب الصفحات...", "ok");
          appendLog(`Starting export for bookId=${bookId} tabMode=${tabMode}`);

          let records = [];
          if (tabMode === "both") {
            appendLog("Fetching home tab");
            records.push(...(await fetchPaged("home")));
            appendLog("Fetching specialist tab");
            records.push(...(await fetchPaged("specialist")));
          } else {
            records = await fetchPaged(tabMode);
          }

          const filtered = records.filter((row) => String(row?.bookId ?? "") === bookId);
          const deduped = dedupeHadith(filtered);
          els.uniqueCount.textContent = String(deduped.length);

          const stamp = new Date().toISOString().replaceAll(":", "-").replace(/\.\d{3}Z$/, "Z");
          const filename = `hadith-export-book-${bookId}-${stamp}.json`;
          const output = {
            metadata: {
              exportedAt: new Date().toISOString(),
              source: "/v1/site/hadith/search",
              bookId,
              bookLabel: bookText,
              searchValue: els.searchValue.value.trim() || "*",
              tabMode,
              removeHtml: els.removeHtml.checked,
              pagesFetched: Number(els.pagesCount.textContent),
              rawCount: records.length,
              filteredByBookCount: filtered.length,
              uniqueCount: deduped.length,
            },
            data: deduped,
          };

          downloadJson(filename, output);

          if (stopRequested) {
            setStatus("تم الإيقاف. الملف قد يحتوي نتائج جزئية.", "error");
          } else {
            setStatus(`اكتمل التصدير. تم تنزيل ${filename}`, "ok");
          }
          appendLog(`Download ready: ${filename}`);
        } catch (error) {
          console.error(error);
          setStatus(`فشل التصدير: ${error.message}`, "error");
          appendLog(`ERROR: ${error.message}`);
        } finally {
          setBusy(false);
        }
      }

      els.reloadBooks.addEventListener("click", async () => {
        try {
          await loadBooks();
        } catch (error) {
          setStatus(`تعذر تحميل الكتب: ${error.message}`, "error");
          appendLog(`ERROR loading books: ${error.message}`);
        }
      });

      els.startExport.addEventListener("click", startExport);
      els.stopExport.addEventListener("click", () => {
        stopRequested = true;
        setStatus("سيتم الإيقاف بعد انتهاء الطلب الحالي...");
      });

      loadBooks().catch((error) => {
        setStatus(`تعذر تحميل الكتب: ${error.message}`, "error");
        appendLog(`ERROR loading books: ${error.message}`);
      });
    </script>
  </body>
</html>
