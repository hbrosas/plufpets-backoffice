<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Open cash drawer</title>
    <style>
        body { font-family: ui-sans-serif, system-ui, sans-serif; margin: 24px; color: #14231f; }
        p { margin: 0; font-size: 14px; }
        @page { size: 80mm 30mm; margin: 4mm; }
    </style>
</head>
<body>
    <p>Open cash drawer</p>
    <script>
        window.addEventListener('load', () => {
            window.print();
        });
    </script>
</body>
</html>
