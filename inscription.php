<?php
// ─── Bootstrap ───────────────────────────────────────────────────────────────
session_start();
require __DIR__ . '/config.php';

// CSRF token
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// ─── PDO helper ──────────────────────────────────────────────────────────────
function db(): PDO {
    static $pdo;
    if (!$pdo) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

// ─── Courses list ────────────────────────────────────────────────────────────
$courses = [
    'Cours fondamentaux – Jeudi 19h15 (Salle des Augustins)',
    'Pratique tous niveaux – Jeudi 20h30 (Salle des Augustins)',
    'Cours intermédiaires – Mardi 19h15 (Danse District)',
    'Cours avancés – Mardi 20h30 (Danse District)',
];

// ─── State ───────────────────────────────────────────────────────────────────
$errors  = [];
$success = false;
$values  = ['nom' => '', 'email' => '', 'telephone' => '', 'cours' => '', 'message' => ''];

// ─── Handle POST ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF check
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $errors['global'] = 'Erreur de sécurité. Veuillez recharger la page et réessayer.';
    } else {
        $nom       = trim(strip_tags($_POST['nom']       ?? ''));
        $email     = trim($_POST['email']     ?? '');
        $telephone = trim(strip_tags($_POST['telephone'] ?? ''));
        $cours     = trim($_POST['cours']     ?? '');
        $message   = trim(strip_tags($_POST['message']   ?? ''));
        $values    = compact('nom', 'email', 'telephone', 'cours', 'message');

        // Validation
        if (mb_strlen($nom) < 2) {
            $errors['nom'] = 'Veuillez entrer votre nom complet.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Adresse e-mail invalide.';
        }
        if (!in_array($cours, $courses, true)) {
            $errors['cours'] = 'Veuillez sélectionner un cours.';
        }

        // File upload
        $fichierNom  = null;
        $fichierPath = null;
        $file = $_FILES['document'] ?? null;

        if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
            $errors['document'] = 'Veuillez joindre une copie de votre pièce d\'identité.';
        } elseif ($file['error'] !== UPLOAD_ERR_OK) {
            $errors['document'] = 'Erreur lors du téléchargement (code ' . $file['error'] . ').';
        } elseif ($file['size'] > MAX_FILE_SIZE) {
            $errors['document'] = 'Le fichier dépasse la taille maximale de 5 Mo.';
        } else {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ALLOWED_EXT, true)) {
                $errors['document'] = 'Format accepté : JPG, PNG, WEBP ou PDF.';
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime  = $finfo->file($file['tmp_name']);
                if (!in_array($mime, ALLOWED_MIME, true)) {
                    $errors['document'] = 'Le contenu du fichier ne correspond pas à une image ou un PDF valide.';
                } else {
                    $fichierNom  = $file['name'];
                    $fichierPath = bin2hex(random_bytes(16)) . '.' . $ext;
                }
            }
        }

        // Persist if no errors
        if (empty($errors)) {
            if (!is_dir(UPLOAD_DIR)) {
                mkdir(UPLOAD_DIR, 0750, true);
            }
            move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $fichierPath);

            $stmt = db()->prepare(
                'INSERT INTO inscriptions (nom, email, telephone, cours, message, fichier_nom, fichier)
                 VALUES (:nom, :email, :telephone, :cours, :message, :fichier_nom, :fichier)'
            );
            $stmt->execute([
                ':nom'         => $nom,
                ':email'       => $email,
                ':telephone'   => $telephone,
                ':cours'       => $cours,
                ':message'     => $message ?: null,
                ':fichier_nom' => $fichierNom,
                ':fichier'     => $fichierPath,
            ]);

            sendConfirmation($nom, $email, $cours);
            sendAdminNotification($nom, $email, $telephone, $cours);

            // Regenerate CSRF after successful submit
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            $success = true;
            $values  = ['nom' => '', 'email' => '', 'telephone' => '', 'cours' => '', 'message' => ''];
        }
    }
}

// ─── Mailer helpers ──────────────────────────────────────────────────────────
function sendConfirmation(string $nom, string $email, string $cours): void {
    $subject = 'Flor de Tango – Confirmation de votre inscription';
    $body =
        "Bonjour {$nom},\r\n\r\n" .
        "Nous avons bien reçu votre demande d'inscription pour le cours :\r\n" .
        "  {$cours}\r\n\r\n" .
        "Votre dossier est en cours d'examen. Nous vous enverrons un e-mail\r\n" .
        "dès que votre inscription sera validée.\r\n\r\n" .
        "À bientôt sur la piste !\r\n" .
        "Céline & Édouard — Flor de Tango\r\n" .
        ADMIN_EMAIL . "\r\n";

    $headers  = 'From: ' . SITE_NAME . ' <' . MAIL_FROM . ">\r\n";
    $headers .= 'Reply-To: ' . ADMIN_EMAIL . "\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    mail($email, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
}

function sendAdminNotification(string $nom, string $email, string $tel, string $cours): void {
    $subject = '[Flor de Tango] Nouvelle inscription – ' . $nom;
    $body =
        "Nouvelle inscription reçue.\r\n\r\n" .
        "Nom       : {$nom}\r\n" .
        "E-mail    : {$email}\r\n" .
        "Téléphone : " . ($tel ?: '—') . "\r\n" .
        "Cours     : {$cours}\r\n\r\n" .
        "→ Tableau d'administration :\r\n" .
        SITE_URL . "/admin.php\r\n";

    $headers  = 'From: ' . SITE_NAME . ' <' . MAIL_FROM . ">\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    mail(ADMIN_EMAIL, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
}

// ─── Helper: safe HTML output ────────────────────────────────────────────────
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="Inscrivez-vous aux cours de tango argentin de Flor de Tango à Grasse." />
  <title>Inscription aux cours – Flor de Tango</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400;1,700&family=Lato:wght@300;400;700&display=swap" rel="stylesheet" />
  <style>
    :root {
      --rouge:      #8B1A1A;
      --rouge-clair:#a82222;
      --or:         #C9A84C;
      --or-clair:   #dfc06a;
      --noir:       #0E0E0E;
      --noir-soft:  #111111;
      --creme:      #FAF6EF;
      --creme-dark: #f0e9da;
      --gris:       #3a3a3a;
      --gris-clair: #888;
      --success:    #2d7a4f;
      --error:      #a82222;
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; }

    body {
      font-family: 'Lato', sans-serif;
      font-weight: 300;
      background: var(--noir);
      color: var(--creme);
      line-height: 1.7;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    /* ── NAV ─────────────────────────────────────────────── */
    nav {
      position: sticky;
      top: 0;
      z-index: 100;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 1.2rem 4rem;
      background: rgba(14,14,14,0.96);
      backdrop-filter: blur(10px);
      border-bottom: 1px solid rgba(201,168,76,0.2);
    }
    .nav-logo {
      font-family: 'Playfair Display', serif;
      font-size: 1.3rem;
      font-weight: 700;
      color: var(--or);
      text-decoration: none;
    }
    .nav-back {
      font-size: 0.78rem;
      font-weight: 700;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      color: var(--creme);
      text-decoration: none;
      opacity: 0.65;
      transition: opacity 0.2s, color 0.2s;
    }
    .nav-back:hover { opacity: 1; color: var(--or); }

    /* ── PAGE HEADER ─────────────────────────────────────── */
    .page-header {
      background: var(--noir-soft);
      border-bottom: 1px solid rgba(201,168,76,0.1);
      padding: 4rem 2rem 3.5rem;
      text-align: center;
    }
    .section-label {
      display: block;
      font-size: 0.7rem;
      font-weight: 700;
      letter-spacing: 0.3em;
      text-transform: uppercase;
      color: var(--or);
      margin-bottom: 0.8rem;
    }
    .page-title {
      font-family: 'Playfair Display', serif;
      font-size: clamp(2rem, 5vw, 3rem);
      font-weight: 700;
      color: var(--creme);
      margin-bottom: 1rem;
    }
    .divider {
      width: 48px;
      height: 2px;
      background: var(--or);
      margin: 0 auto 1.5rem;
    }
    .page-subtitle {
      font-size: 1rem;
      color: rgba(250,246,239,0.6);
      max-width: 560px;
      margin: 0 auto;
    }

    /* ── MAIN ────────────────────────────────────────────── */
    main { flex: 1; padding: 4rem 2rem; }
    .inner {
      max-width: 660px;
      margin: 0 auto;
    }

    /* ── SUCCESS BANNER ──────────────────────────────────── */
    .success-box {
      background: rgba(45,122,79,0.12);
      border: 1px solid rgba(45,122,79,0.4);
      padding: 2.5rem;
      text-align: center;
    }
    .success-box h2 {
      font-family: 'Playfair Display', serif;
      font-size: 1.6rem;
      color: #6fcf97;
      margin-bottom: 1rem;
    }
    .success-box p { color: rgba(250,246,239,0.75); margin-bottom: 0.6rem; }
    .success-box a { color: var(--or); }

    /* ── FORM CARD ───────────────────────────────────────── */
    .form-card {
      background: #161616;
      padding: 2.5rem;
    }
    .form-card-title {
      font-family: 'Playfair Display', serif;
      font-size: 1.3rem;
      font-weight: 700;
      color: var(--creme);
      margin-bottom: 0.4rem;
    }
    .form-card-sub {
      font-size: 0.85rem;
      color: rgba(250,246,239,0.45);
      margin-bottom: 2rem;
    }
    .form-group { margin-bottom: 1.3rem; }
    .form-group label {
      display: block;
      font-size: 0.72rem;
      font-weight: 700;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      color: rgba(250,246,239,0.5);
      margin-bottom: 0.45rem;
    }
    .form-group label .req { color: var(--or); margin-left: 2px; }

    .form-group input,
    .form-group select,
    .form-group textarea {
      width: 100%;
      background: rgba(255,255,255,0.04);
      border: 1px solid rgba(255,255,255,0.1);
      color: var(--creme);
      font-family: 'Lato', sans-serif;
      font-size: 0.95rem;
      font-weight: 300;
      padding: 0.85rem 1rem;
      outline: none;
      transition: border-color 0.2s;
      appearance: none;
    }
    .form-group select {
      cursor: pointer;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%23C9A84C' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 1rem center;
      padding-right: 2.5rem;
    }
    .form-group select option { background: #1a1a1a; }
    .form-group textarea { height: 110px; resize: vertical; }

    .form-group input::placeholder,
    .form-group textarea::placeholder { color: rgba(250,246,239,0.2); }
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus { border-color: var(--or); }

    .field-error {
      border-color: var(--error) !important;
    }
    .error-msg {
      font-size: 0.78rem;
      color: #f28b82;
      margin-top: 0.35rem;
    }

    /* File input */
    .file-label {
      display: flex;
      align-items: center;
      gap: 1rem;
      background: rgba(255,255,255,0.04);
      border: 1px solid rgba(255,255,255,0.1);
      padding: 0.85rem 1rem;
      cursor: pointer;
      transition: border-color 0.2s;
    }
    .file-label:hover { border-color: var(--or); }
    .file-label input[type="file"] { display: none; }
    .file-icon {
      font-size: 1.2rem;
      flex-shrink: 0;
    }
    .file-text {
      font-size: 0.88rem;
      color: rgba(250,246,239,0.5);
    }
    .file-text strong { color: var(--or); font-weight: 700; }
    .file-name {
      font-size: 0.82rem;
      color: rgba(250,246,239,0.7);
      margin-top: 0.4rem;
    }
    .file-hint {
      font-size: 0.75rem;
      color: rgba(250,246,239,0.3);
      margin-top: 0.4rem;
    }

    /* Global error */
    .global-error {
      background: rgba(168,34,34,0.12);
      border: 1px solid rgba(168,34,34,0.35);
      padding: 1rem 1.2rem;
      font-size: 0.88rem;
      color: #f28b82;
      margin-bottom: 1.5rem;
    }

    /* Submit */
    .form-submit {
      width: 100%;
      background: var(--rouge);
      color: var(--creme);
      border: 2px solid var(--rouge);
      font-family: 'Lato', sans-serif;
      font-size: 0.82rem;
      font-weight: 700;
      letter-spacing: 0.15em;
      text-transform: uppercase;
      padding: 1rem;
      cursor: pointer;
      transition: all 0.25s;
      margin-top: 0.5rem;
    }
    .form-submit:hover {
      background: var(--rouge-clair);
      border-color: var(--rouge-clair);
    }

    /* Info note */
    .info-note {
      font-size: 0.78rem;
      color: rgba(250,246,239,0.35);
      text-align: center;
      margin-top: 1.2rem;
    }

    /* ── FOOTER ──────────────────────────────────────────── */
    footer {
      background: #070707;
      border-top: 1px solid rgba(201,168,76,0.15);
      padding: 1.8rem 4rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 1rem;
    }
    .footer-logo {
      font-family: 'Playfair Display', serif;
      font-size: 1.1rem;
      font-weight: 700;
      color: var(--or);
    }
    .footer-copy { font-size: 0.78rem; color: rgba(250,246,239,0.3); }

    /* ── RESPONSIVE ──────────────────────────────────────── */
    @media (max-width: 600px) {
      nav { padding: 1rem 1.5rem; }
      .form-card { padding: 1.8rem 1.5rem; }
      footer { flex-direction: column; text-align: center; padding: 1.5rem; }
    }
  </style>
</head>
<body>

<!-- ══════ NAVIGATION ══════ -->
<nav>
  <a href="index.html" class="nav-logo">Flor de Tango</a>
  <a href="index.html" class="nav-back">← Retour au site</a>
</nav>

<!-- ══════ PAGE HEADER ══════ -->
<div class="page-header">
  <span class="section-label">Saison 2025 – 2026</span>
  <h1 class="page-title">Inscription aux cours</h1>
  <div class="divider"></div>
  <p class="page-subtitle">
    Remplissez le formulaire ci-dessous et joignez une pièce d'identité.<br>
    Nous validerons votre inscription et vous en informerons par e-mail.
  </p>
</div>

<!-- ══════ MAIN ══════ -->
<main>
  <div class="inner">

    <?php if ($success): ?>
    <!-- ── SUCCESS ── -->
    <div class="success-box">
      <h2>Demande envoyée !</h2>
      <p>Merci <strong><?= h($nom ?? '') ?></strong>, votre dossier a bien été reçu.</p>
      <p>Vous recevrez un e-mail de confirmation à <strong><?= h($email ?? '') ?></strong>.</p>
      <p>Nous vous contacterons dès validation de votre inscription.</p>
      <p style="margin-top:1.8rem;">
        <a href="index.html">← Retour au site</a>
      </p>
    </div>

    <?php else: ?>
    <!-- ── FORM ── -->
    <div class="form-card">
      <p class="form-card-title">Votre demande d'inscription</p>
      <p class="form-card-sub">Tous les champs marqués <span style="color:var(--or)">*</span> sont obligatoires.</p>

      <?php if (!empty($errors['global'])): ?>
        <div class="global-error"><?= h($errors['global']) ?></div>
      <?php endif; ?>

      <form method="POST" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>" />
        <input type="hidden" name="MAX_FILE_SIZE" value="<?= MAX_FILE_SIZE ?>" />

        <!-- Nom -->
        <div class="form-group">
          <label for="nom">Nom complet <span class="req">*</span></label>
          <input
            type="text" id="nom" name="nom"
            value="<?= h($values['nom']) ?>"
            placeholder="Prénom Nom"
            class="<?= isset($errors['nom']) ? 'field-error' : '' ?>"
            required
          />
          <?php if (isset($errors['nom'])): ?>
            <p class="error-msg"><?= h($errors['nom']) ?></p>
          <?php endif; ?>
        </div>

        <!-- Email -->
        <div class="form-group">
          <label for="email">Adresse e-mail <span class="req">*</span></label>
          <input
            type="email" id="email" name="email"
            value="<?= h($values['email']) ?>"
            placeholder="votre@email.com"
            class="<?= isset($errors['email']) ? 'field-error' : '' ?>"
            required
          />
          <?php if (isset($errors['email'])): ?>
            <p class="error-msg"><?= h($errors['email']) ?></p>
          <?php endif; ?>
        </div>

        <!-- Téléphone -->
        <div class="form-group">
          <label for="telephone">Téléphone</label>
          <input
            type="tel" id="telephone" name="telephone"
            value="<?= h($values['telephone']) ?>"
            placeholder="06 00 00 00 00"
          />
        </div>

        <!-- Cours -->
        <div class="form-group">
          <label for="cours">Cours souhaité <span class="req">*</span></label>
          <select
            id="cours" name="cours"
            class="<?= isset($errors['cours']) ? 'field-error' : '' ?>"
            required
          >
            <option value="" disabled <?= $values['cours'] === '' ? 'selected' : '' ?>>— Choisissez un cours —</option>
            <?php foreach ($courses as $c): ?>
              <option value="<?= h($c) ?>" <?= $values['cours'] === $c ? 'selected' : '' ?>>
                <?= h($c) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if (isset($errors['cours'])): ?>
            <p class="error-msg"><?= h($errors['cours']) ?></p>
          <?php endif; ?>
        </div>

        <!-- Document -->
        <div class="form-group">
          <label>Pièce d'identité <span class="req">*</span></label>
          <label class="file-label <?= isset($errors['document']) ? 'field-error' : '' ?>" for="document">
            <span class="file-icon">📎</span>
            <div>
              <span class="file-text"><strong>Cliquez pour choisir un fichier</strong></span>
              <input type="file" id="document" name="document" accept=".jpg,.jpeg,.png,.webp,.pdf" />
            </div>
          </label>
          <p class="file-name" id="file-name-display">Aucun fichier sélectionné</p>
          <p class="file-hint">Photo ou scan de votre carte d'identité ou passeport — JPG, PNG, WEBP ou PDF — max 5 Mo</p>
          <?php if (isset($errors['document'])): ?>
            <p class="error-msg"><?= h($errors['document']) ?></p>
          <?php endif; ?>
        </div>

        <!-- Message -->
        <div class="form-group">
          <label for="message">Message ou question (optionnel)</label>
          <textarea id="message" name="message" placeholder="Votre niveau de danse, vos disponibilités, questions…"><?= h($values['message']) ?></textarea>
        </div>

        <button type="submit" class="form-submit">Envoyer ma demande d'inscription</button>
        <p class="info-note">
          Vos données sont utilisées uniquement dans le cadre de la gestion des inscriptions.
        </p>
      </form>
    </div>
    <?php endif; ?>

  </div>
</main>

<!-- ══════ FOOTER ══════ -->
<footer>
  <div class="footer-logo">Flor de Tango</div>
  <p class="footer-copy">© 2026 Flor de Tango · Association de danse · Grasse</p>
</footer>

<script>
  // Show selected filename in the custom file input
  document.getElementById('document').addEventListener('change', function () {
    const display = document.getElementById('file-name-display');
    display.textContent = this.files.length ? this.files[0].name : 'Aucun fichier sélectionné';
  });
</script>
</body>
</html>
