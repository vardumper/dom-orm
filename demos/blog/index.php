<?php

declare(strict_types=1);

// Leaf's exception handler uses (integer) cast, deprecated in PHP 8.5.
// Suppress deprecations so the handler itself doesn't crash.
error_reporting(E_ALL & ~E_DEPRECATED);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/models/Comment.php';
require __DIR__ . '/models/Image.php';
require __DIR__ . '/models/Article.php';
require __DIR__ . '/service/BlogManager.php';

\putenv('DOM_ORM_FLYSYSTEM_LOCATION=' . __DIR__ . '/storage');
\putenv('DOM_ORM_FILENAME=data.xml');

$storageDir = __DIR__ . '/storage';
if (!\is_dir($storageDir) && !\mkdir($storageDir, 0755, true) && !\is_dir($storageDir)) {
    throw new \RuntimeException('Unable to create storage directory: ' . $storageDir);
}

// Seed sample data only on first run.
if (!\is_file($storageDir . '/data.xml')) {
    $blog = new BlogManager();

    $imagePath = __DIR__ . '/assets/images/dummy-image-800x400.jpg';
    $seedImage = new Image(
        'dummy-image-800x400.jpg',
        'image/jpeg',
        base64_encode((string)file_get_contents($imagePath)),
        (int)filesize($imagePath),
    );

    $id1 = $blog->createArticle(
        'Hello World!',
        'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Duis autem vel eum iriure dolor in hendrerit in vulputate velit esse molestie consequat, vel illum dolore eu feugiat nulla facilisis at vero eros et accumsan et iusto odio dignissim qui blandit praesent luptatum zzril delenit augue duis dolore te feugait nulla facilisi. Lorem ipsum dolor sit amet, consectetuer' . "\n\n" . 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam',
        'Alice',
        'This is your first post. Edit or delete it.',
        $seedImage,
    );
    $blog->addComment($id1, 'Bob', 'Great intro, very clear!');
    $blog->addComment($id1, 'Carol', 'Looking forward to trying this out.');

    $imagePath = __DIR__ . '/assets/images/small-landscape.jpg';
    $seedImage = new Image(
        'small-landscape.jpg',
        'image/jpeg',
        base64_encode((string)file_get_contents($imagePath)),
        (int)filesize($imagePath),
    );
    $id2 = $blog->createArticle(
        'Another blog post',
        'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt sed diam voluptua. At vero eos et accusam et. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Duis autem vel eum iriure dolor in hendrerit in vulputate velit esse molestie consequat, vel illum dolore eu feugiat nulla facilisis at vero eros et accumsan et iusto odio dignissim qui blandit praesent luptatum zzril delenit augue duis dolore te feugait nulla facilisi. Lorem ipsum dolor sit amet, consectetuer' . "\n\n" . 'Lorem ipsum dolor sit amet sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet.',
        'Bob',
        'Actually this was really your first post – the oarticle on top is lying.',
        $seedImage,
    );
}

// ── Helpers ──────────────────────────────────────────────────────────────────

function jsonOk(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function jsonError(string $message, int $status = 400): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => $message,
    ]);
    exit;
}

// ── Base path detection ────────────────────────────────────────────────────
// Detect the directory the app is served from (e.g. /blog or /) so asset
// URLs work both locally (php -S) and when deployed in a subdirectory.
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
$basePath = rtrim(dirname($scriptName), '/');

// Tell Leaf's router about the sub-directory so routes like '/' and '/admin'
// match correctly when the app is served from e.g. /blog/.
if ($basePath !== '') {
    \Leaf\Router::setBasePath($basePath);
}

// ── Static assets (for php -S) ───────────────────────────────────────────────
// The built-in dev server doesn't serve subdirectory files automatically when
// index.php is the router, so we handle /assets/* here.
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
// Strip basePath prefix so the match works in both / and /blog contexts.
$assetUri = $basePath !== '' ? substr($uri, strlen($basePath)) : $uri;
if (str_starts_with($assetUri, '/assets/')) {
    $uri = $assetUri;
    $file = __DIR__ . $uri;
    if (is_file($file)) {
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        $mime = match ($ext) {
            'css' => 'text/css',
            'js' => 'application/javascript',
            'woff2' => 'font/woff2',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };
        header('Content-Type: ' . $mime);
        readfile($file);
        exit;
    }
}

// ── Twig setup ───────────────────────────────────────────────────────────────

$twig = new \Twig\Environment(
    new \Twig\Loader\FilesystemLoader(__DIR__ . '/templates'),
    [
        'autoescape' => 'html',
    ],
);
$twig->addGlobal('basePath', $basePath);

// ── Routes ───────────────────────────────────────────────────────────────────

app()->get('/', function () use ($twig) {
    $articles = (new BlogManager())->findAllArticles();
    echo $twig->render('index.twig', [
        'articles' => $articles,
    ]);
});

app()->get('/admin', function () use ($twig) {
    $articles = (new BlogManager())->findAllArticles();
    echo $twig->render('admin/index.twig', [
        'articles' => $articles,
    ]);
});

app()->post('/api/article/add', function () {
    $title = trim((string)($_POST['title'] ?? ''));
    $body = trim((string)($_POST['body'] ?? ''));
    $author = trim((string)($_POST['author'] ?? ''));
    $subline = trim((string)($_POST['subline'] ?? ''));

    if ($title === '' || $body === '' || $author === '') {
        jsonError('title, body and author are required');
    }

    $image = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['image']['tmp_name']);
        if (!in_array($mime, $allowed, true)) {
            jsonError('Only JPEG, PNG, GIF and WebP images are allowed');
        }
        if ($_FILES['image']['size'] > 2 * 1024 * 1024) {
            jsonError('Image must be smaller than 2 MB');
        }
        $image = new Image(
            basename($_FILES['image']['name']),
            $mime,
            base64_encode((string)file_get_contents($_FILES['image']['tmp_name'])),
            (int)$_FILES['image']['size'],
        );
    }

    try {
        $id = (new BlogManager())->createArticle($title, $body, $author, $subline, $image);
        jsonOk([
            'success' => true,
            'id' => $id,
        ], 201);
    } catch (\Throwable $e) {
        jsonError($e->getMessage());
    }
});

app()->post('/api/article/remove', function () {
    $id = trim((string)(request()->get('id') ?? ''));

    if ($id === '') {
        jsonError('id is required');
    }

    try {
        (new BlogManager())->removeArticle($id);
        jsonOk([
            'success' => true,
        ]);
    } catch (\Throwable $e) {
        jsonError($e->getMessage());
    }
});

app()->post('/api/comment/add', function () {
    $articleId = trim((string)($_POST['articleId'] ?? ''));
    $author = trim((string)($_POST['author'] ?? ''));
    $body = trim((string)($_POST['body'] ?? ''));

    if ($articleId === '' || $author === '' || $body === '') {
        jsonError('articleId, author and body are required');
    }

    try {
        $id = (new BlogManager())->addComment($articleId, $author, $body);
        jsonOk([
            'success' => true,
            'id' => $id,
        ], 201);
    } catch (\Throwable $e) {
        jsonError($e->getMessage());
    }
});

app()->post('/api/comment/remove', function () {
    $articleId = trim((string)(request()->get('articleId') ?? ''));
    $commentId = trim((string)(request()->get('commentId') ?? ''));

    if ($articleId === '' || $commentId === '') {
        jsonError('articleId and commentId are required');
    }

    try {
        (new BlogManager())->removeComment($articleId, $commentId);
        jsonOk([
            'success' => true,
        ]);
    } catch (\Throwable $e) {
        jsonError($e->getMessage());
    }
});

app()->run();
