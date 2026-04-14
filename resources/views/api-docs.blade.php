<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dorar Laravel API Docs</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css" />
    <style>
      body {
        margin: 0;
        background: #f5f7fb;
        font-family: "Segoe UI", Tahoma, sans-serif;
      }

      .topbar {
        background: #0f172a;
        color: #e2e8f0;
        padding: 14px 20px;
        border-bottom: 1px solid #1e293b;
      }

      .topbar a {
        color: #93c5fd;
        text-decoration: none;
      }

      #swagger-ui {
        max-width: 1200px;
        margin: 0 auto;
      }
    </style>
  </head>
  <body>
    <div class="topbar">
      <strong>Dorar Laravel API</strong>
      <span style="margin-left: 10px;">OpenAPI docs powered by Swagger UI.</span>
      <span style="margin-left: 10px;">Spec:</span>
      <a href="/api-docs/openapi.yaml" target="_blank" rel="noreferrer">/api-docs/openapi.yaml</a>
    </div>

    <div id="swagger-ui"></div>

    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
    <script>
      window.onload = function () {
        window.ui = SwaggerUIBundle({
          url: '/api-docs/openapi.yaml',
          dom_id: '#swagger-ui',
          deepLinking: true,
          tryItOutEnabled: true,
          displayRequestDuration: true,
        });
      };
    </script>
  </body>
</html>
