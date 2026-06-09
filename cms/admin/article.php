<?php
require '../includes/database-connection.php';
require '../includes/functions.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// 기본 에러 배열
$errors = [
    'warning'    => '',
    'title'      => '',
    'summary'    => '',
    'content'    => '',
    'author'     => '',
    'category'   => '',
    'image_file' => '',
    'image_alt'  => '',
];

// 새 글 기본값
$article = [
    'id'          => null,
    'title'       => '',
    'summary'     => '',
    'content'     => '',
    'member_id'   => '',
    'category_id' => '',
    'published'   => 0,
    'image_id'    => null,
    'image_file'  => '',
    'image_alt'   => '',
];

// 작성자 목록 가져오기
$sql = "SELECT id, forename, surname FROM member ORDER BY forename, surname";
$authors = $pdo->query($sql)->fetchAll();

// 카테고리 목록 가져오기
$sql = "SELECT id, name FROM category ORDER BY name";
$categories = $pdo->query($sql)->fetchAll();

// id가 있으면 기존 기사 정보 가져오기
if ($id) {
    $sql = "SELECT a.id, a.title, a.summary, a.content, a.member_id, a.category_id,
                   a.published, a.image_id,
                   i.file AS image_file, i.alt AS image_alt
            FROM article AS a
            LEFT JOIN image AS i ON a.image_id = i.id
            WHERE a.id = :id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id]);
    $article = $stmt->fetch();

    if (!$article) {
        redirect('articles.php', ['failure' => 'Article not found']);
    }
}

// 저장 버튼을 눌렀을 때
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $article['title']       = trim($_POST['title'] ?? '');
    $article['summary']     = trim($_POST['summary'] ?? '');
    $article['content']     = trim($_POST['content'] ?? '');
    $article['member_id']   = filter_input(INPUT_POST, 'member_id', FILTER_VALIDATE_INT);
    $article['category_id'] = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    $article['published']   = isset($_POST['published']) ? 1 : 0;

    // 입력값 검사
    if ($article['title'] === '') {
        $errors['title'] = 'Title is required';
    }

    if ($article['summary'] === '') {
        $errors['summary'] = 'Summary is required';
    }

    if ($article['content'] === '') {
        $errors['content'] = 'Content is required';
    }

    if (!$article['member_id']) {
        $errors['author'] = 'Author is required';
    }

    if (!$article['category_id']) {
        $errors['category'] = 'Category is required';
    }

    // 이미지 업로드 여부 확인
    $upload_image = false;
    $image_alt = trim($_POST['image_alt'] ?? '');

    if (!$article['image_file'] && isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $upload_image = true;
        $image = $_FILES['image'];

        $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $max_size = 1024 * 1024 * 2; // 2MB

        if ($image['error'] !== UPLOAD_ERR_OK) {
            $errors['image_file'] = 'Image upload failed';
        } else {
            $file_info = pathinfo($image['name']);
            $extension = strtolower($file_info['extension'] ?? '');
            $mime_type = mime_content_type($image['tmp_name']);

            if (!in_array($mime_type, $allowed_mime_types)) {
                $errors['image_file'] = 'Only image files can be uploaded';
            } elseif (!in_array($extension, $allowed_extensions)) {
                $errors['image_file'] = 'Invalid image file extension';
            } elseif ($image['size'] > $max_size) {
                $errors['image_file'] = 'Image file size must be 2MB or less';
            }
        }

        if ($image_alt === '') {
            $errors['image_alt'] = 'Alt text is required';
        }
    }

    // 에러 확인
    $invalid = implode('', $errors);

    if ($invalid) {
        $errors['warning'] = 'Please correct the errors below';
    } else {
        // 이미지 업로드 처리
        if ($upload_image) {
            $image = $_FILES['image'];

            $filename = create_filename($image['name'], '../uploads/');
            move_uploaded_file($image['tmp_name'], '../uploads/' . $filename);

            $sql = "INSERT INTO image (file, alt)
                    VALUES (:file, :alt)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'file' => $filename,
                'alt'  => $image_alt,
            ]);

            $article['image_id'] = $pdo->lastInsertId();
            $article['image_file'] = $filename;
            $article['image_alt'] = $image_alt;
        }

        // id가 있으면 수정, 없으면 추가
        if ($id) {
            $sql = "UPDATE article
                    SET title       = :title,
                        summary     = :summary,
                        content     = :content,
                        member_id   = :member_id,
                        category_id = :category_id,
                        image_id    = :image_id,
                        published   = :published
                    WHERE id = :id";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'title'       => $article['title'],
                'summary'     => $article['summary'],
                'content'     => $article['content'],
                'member_id'   => $article['member_id'],
                'category_id' => $article['category_id'],
                'image_id'    => $article['image_id'],
                'published'   => $article['published'],
                'id'          => $id,
            ]);

            redirect('articles.php', ['success' => 'Article updated']);
        } else {
            $sql = "INSERT INTO article
                    (title, summary, content, member_id, category_id, image_id, published)
                    VALUES
                    (:title, :summary, :content, :member_id, :category_id, :image_id, :published)";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'title'       => $article['title'],
                'summary'     => $article['summary'],
                'content'     => $article['content'],
                'member_id'   => $article['member_id'],
                'category_id' => $article['category_id'],
                'image_id'    => $article['image_id'],
                'published'   => $article['published'],
            ]);

            redirect('articles.php', ['success' => 'Article added']);
        }
    }
}
?>
<?php include '../includes/admin-header.php'; ?>

  <form action="article.php<?= $id ? '?id=' . $id : '' ?>" method="POST" enctype="multipart/form-data">
    <main class="container admin" id="content">

      <h1><?= $id ? 'Edit Article' : 'Add Article' ?></h1>

      <?php if ($errors['warning']) { ?>
        <div class="alert alert-danger"><?= $errors['warning'] ?></div>
      <?php } ?>

      <div class="admin-article">
        <section class="image">
          <?php if (!$article['image_file']) { ?>
            <label for="image">Upload image:</label>
            <div class="form-group image-placeholder">
              <input type="file" name="image" class="form-control-file" id="image"><br>
              <span class="errors"><?= $errors['image_file'] ?></span>
            </div>
            <div class="form-group">
              <label for="image_alt">Alt text: </label>
              <input type="text" name="image_alt" id="image_alt" value="<?= html_escape($_POST['image_alt'] ?? '') ?>" class="form-control">
              <span class="errors"><?= $errors['image_alt'] ?></span>
            </div>
          <?php } else { ?>
            <label>Image:</label>
            <img src="../uploads/<?= html_escape($article['image_file']) ?>"
                 alt="<?= html_escape($article['image_alt']) ?>">
            <p class="alt"><strong>Alt text:</strong> <?= html_escape($article['image_alt']) ?></p>
            <a href="alt-text-edit.php?id=<?= $article['id'] ?>" class="btn btn-secondary">Edit alt text</a>
            <a href="image-delete.php?id=<?= $id ?>" class="btn btn-secondary">Delete image</a><br><br>
          <?php } ?>
        </section>

        <section class="text">
          <div class="form-group">
            <label for="title">Title: </label>
            <input type="text" name="title" id="title" value="<?= html_escape($article['title']) ?>"
                   class="form-control">
            <span class="errors"><?= $errors['title'] ?></span>
          </div>

          <div class="form-group">
            <label for="summary">Summary: </label>
            <textarea name="summary" id="summary"
                      class="form-control"><?= html_escape($article['summary']) ?></textarea>
            <span class="errors"><?= $errors['summary'] ?></span>
          </div>

          <div class="form-group">
            <label for="content">Content: </label>
            <textarea name="content" id="content"
                      class="form-control"><?= html_escape($article['content']) ?></textarea>
            <span class="errors"><?= $errors['content'] ?></span>
          </div>

          <div class="form-group">
            <label for="member_id">Author: </label>
            <select name="member_id" id="member_id">
              <?php foreach ($authors as $author) { ?>
                <option value="<?= $author['id'] ?>"
                    <?= ($article['member_id'] == $author['id']) ? 'selected' : ''; ?>>
                    <?= html_escape($author['forename'] . ' ' . $author['surname']) ?>
                </option>
              <?php } ?>
            </select>
            <span class="errors"><?= $errors['author'] ?></span>
          </div>

          <div class="form-group">
            <label for="category">Category: </label>
            <select name="category_id" id="category">
              <?php foreach ($categories as $category) { ?>
                <option value="<?= $category['id'] ?>"
                    <?= ($article['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                    <?= html_escape($category['name']) ?>
                </option>
              <?php } ?>
            </select>
            <span class="errors"><?= $errors['category'] ?></span>
          </div>

          <div class="form-check">
            <input type="checkbox" name="published" value="1" class="form-check-input" id="published"
                <?= ($article['published'] == 1) ? 'checked' : ''; ?>>
            <label for="published" class="form-check-label">Published</label>
          </div>

          <input type="submit" name="update" value="Save" class="btn btn-primary">
        </section>
      </div>
    </main>
  </form>

<?php include '../includes/admin-footer.php'; ?>