<?php
// ─── Bootstrap ───────────────────────────────────────────────────────────────
session_start();
require __DIR__ . '/config.php';

// ─── PDO ─────────────────────────────────────────────────────────────────────
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

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ─── Login ───────────────────────────────────────────────────────────────────
$loginError = '';

if (isset($_POST['action']) && $_POST['action'] === 'login') {
    if ($_POST['password'] === ADMIN_PASSWORD) {
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        header('Location: admin.php');
        exit;
    } else {
        $loginError = 'Mot de passe incorrect.';
    }
}

if (!isset($_SESSION['admin'])) {
    // Show login form
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
      <meta charset="UTF-8" />
      <meta name="viewport" content="width=device-width, initial-scale=1.0" />
      <title>Administration – Flor de Tango</title>
      <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Lato:wght@300;400;700&display=swap" rel="stylesheet" />
      <style>
        :root { --rouge:#8B1A1A; --or:#C9A84C; --noir:#0E0E0E; --creme:#FAF6EF; }
        *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
        body {
          font-family:'Lato',sans-serif; font-weight:300;
          background:var(--noir); color:var(--creme);
          min-height:100vh; display:flex; align-items:center; justify-content:center;
        }
        .login-box {
          background:#161616; padding:3rem 2.5rem;
          width:100%; max-width:380px;
          border-top:3px solid var(--rouge);
        }
        .login-title {
          font-family:'Playfair Display',serif; font-size:1.5rem;
          color:var(--creme); margin-bottom:0.3rem;
        }
        .login-sub { font-size:0.82rem; color:rgba(250,246,239,0.4); margin-bottom:2rem; }
        label {
          display:block; font-size:0.7rem; font-weight:700; letter-spacing:0.12em;
          text-transform:uppercase; color:rgba(250,246,239,0.45); margin-bottom:0.5rem;
        }
        input[type="password"] {
          width:100%; background:rgba(255,255,255,0.05);
          border:1px solid rgba(255,255,255,0.1); color:var(--creme);
          font-family:'Lato',sans-serif; font-size:0.95rem;
          padding:0.85rem 1rem; outline:none; transition:border-color 0.2s;
          margin-bottom:1.2rem;
        }
        input[type="password"]:focus { border-color:var(--or); }
        .error { font-size:0.8rem; color:#f28b82; margin-bottom:1rem; }
        button {
          width:100%; background:var(--rouge); color:var(--creme);
          border:none; font-family:'Lato',sans-serif; font-size:0.82rem;
          font-weight:700; letter-spacing:0.15em; text-transform:uppercase;
          padding:1rem; cursor:pointer; transition:background 0.2s;
        }
        button:hover { background:#a82222; }
        .back { display:block; margin-top:1.5rem; text-align:center; font-size:0.78rem; color:rgba(250,246,239,0.3); text-decoration:none; }
        .back:hover { color:var(--or); }
      </style>
    </head>
    <body>
      <div class="login-box">
        <p class="login-title">Administration</p>
        <p class="login-sub">Flor de Tango – Espace privé</p>
        <?php if ($loginError): ?>
          <p class="error"><?= h($loginError) ?></p>
        <?php endif; ?>
        <form method="POST">
          <input type="hidden" name="action" value="login" />
          <label for="pwd">Mot de passe</label>
          <input type="password" id="pwd" name="password" autofocus required />
          <button type="submit">Se connecter</button>
        </form>
        <a href="index.html" class="back">← Retour au site</a>
      </div>
    </body>
    </html>
    <?php
    exit;
}

// ─── Logout ──────────────────────────────────────────────────────────────────
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// ─── Actions ─────────────────────────────────────────────────────────────────
$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'valider') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            // Fetch inscription
            $stmt = db()->prepare('SELECT * FROM inscriptions WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch();

            if ($row && $row['statut'] === 'en_attente') {
                // Update status
                db()->prepare('UPDATE inscriptions SET statut = "valide" WHERE id = :id')
                   ->execute([':id' => $id]);

                // Notify applicant
                sendValidation($row['nom'], $row['email'], $row['cours']);
                $flash = 'Inscription de ' . htmlspecialchars($row['nom']) . ' validée – e-mail envoyé.';
            }
        }
        header('Location: admin.php?flash=' . urlencode($flash));
        exit;
    }
}

if (isset($_GET['flash'])) {
    $flash = $_GET['flash'];
}

// ─── Send validation email ───────────────────────────────────────────────────
function sendValidation(string $nom, string $email, string $cours): void {
    $subject = 'Flor de Tango – Votre inscription est validée !';
    $body =
        "Bonjour {$nom},\r\n\r\n" .
        "Nous avons le plaisir de vous confirmer que votre inscription est validée :\r\n" .
        "  {$cours}\r\n\r\n" .
        "Vous êtes les bienvenu(e) dans notre association. À très bientôt sur la piste !\r\n\r\n" .
        "Céline & Édouard — Flor de Tango\r\n" .
        ADMIN_EMAIL . "\r\n";

    $headers  = 'From: ' . SITE_NAME . ' <' . MAIL_FROM . ">\r\n";
    $headers .= 'Reply-To: ' . ADMIN_EMAIL . "\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    mail($email, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
}

// ─── Fetch data ───────────────────────────────────────────────────────────────
$filtre = $_GET['statut'] ?? 'tous';
$counts = db()->query(
    'SELECT statut, COUNT(*) AS n FROM inscriptions GROUP BY statut'
)->fetchAll();
$total = $enAttente = $valide = 0;
foreach ($counts as $c) {
    $total += $c['n'];
    if ($c['statut'] === 'en_attente') $enAttente = $c['n'];
    if ($c['statut'] === 'valide')     $valide    = $c['n'];
}

$where = $filtre !== 'tous' ? 'WHERE statut = :statut' : '';
$stmt  = db()->prepare("SELECT * FROM inscriptions {$where} ORDER BY cree_le DESC");
if ($filtre !== 'tous') $stmt->bindValue(':statut', $filtre);
$stmt->execute();
$rows = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Administration – Flor de Tango</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Lato:wght@300;400;700&display=swap" rel="stylesheet" />
  <style>
    :root {
      --rouge:#8B1A1A; --rouge-clair:#a82222;
      --or:#C9A84C;
      --noir:#0E0E0E; --noir-soft:#111;
      --creme:#FAF6EF; --gris:#3a3a3a;
      --success:#2d7a4f;
    }
    *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
    body {
      font-family:'Lato',sans-serif; font-weight:300;
      background:#0d0d0d; color:var(--creme);
      min-height:100vh;
    }

    /* ── TOP BAR ─────────── */
    .topbar {
      display:flex; align-items:center; justify-content:space-between;
      padding:1rem 2.5rem;
      background:#161616;
      border-bottom:1px solid rgba(201,168,76,0.15);
    }
    .topbar-brand {
      font-family:'Playfair Display',serif;
      font-size:1.15rem; font-weight:700; color:var(--or);
    }
    .topbar-brand span { font-family:'Lato',sans-serif; font-size:0.7rem; font-weight:700; letter-spacing:0.15em; text-transform:uppercase; color:rgba(250,246,239,0.4); margin-left:0.8rem; }
    .topbar-links { display:flex; gap:1.5rem; align-items:center; }
    .topbar-links a { font-size:0.75rem; font-weight:700; letter-spacing:0.1em; text-transform:uppercase; color:rgba(250,246,239,0.45); text-decoration:none; transition:color 0.2s; }
    .topbar-links a:hover { color:var(--or); }
    .topbar-links a.logout { color:rgba(168,34,34,0.7); }
    .topbar-links a.logout:hover { color:#f28b82; }

    /* ── PAGE ────────────── */
    .page { max-width:1200px; margin:0 auto; padding:2.5rem 2rem; }

    /* ── FLASH ───────────── */
    .flash {
      background:rgba(45,122,79,0.15);
      border:1px solid rgba(45,122,79,0.4);
      padding:0.85rem 1.2rem;
      font-size:0.88rem; color:#6fcf97;
      margin-bottom:1.5rem;
    }

    /* ── STATS ───────────── */
    .stats { display:grid; grid-template-columns:repeat(3,1fr); gap:1.2rem; margin-bottom:2rem; }
    .stat-card {
      background:#161616;
      border-left:3px solid var(--or);
      padding:1.4rem 1.8rem;
    }
    .stat-card.rouge { border-color:var(--rouge); }
    .stat-card.vert  { border-color:#2d7a4f; }
    .stat-num {
      font-family:'Playfair Display',serif;
      font-size:2.5rem; font-weight:700;
      color:var(--creme); line-height:1;
      margin-bottom:0.3rem;
    }
    .stat-label {
      font-size:0.72rem; font-weight:700;
      letter-spacing:0.12em; text-transform:uppercase;
      color:rgba(250,246,239,0.4);
    }

    /* ── FILTER TABS ─────── */
    .filter-tabs { display:flex; gap:0.5rem; margin-bottom:1.5rem; flex-wrap:wrap; }
    .filter-tabs a {
      font-size:0.75rem; font-weight:700;
      letter-spacing:0.1em; text-transform:uppercase;
      padding:0.45rem 1rem; text-decoration:none;
      border:1px solid rgba(255,255,255,0.1);
      color:rgba(250,246,239,0.5);
      transition:all 0.2s;
    }
    .filter-tabs a:hover { border-color:var(--or); color:var(--or); }
    .filter-tabs a.active {
      background:var(--rouge); border-color:var(--rouge); color:var(--creme);
    }

    /* ── TABLE ───────────── */
    .table-wrap { overflow-x:auto; }
    table {
      width:100%; border-collapse:collapse;
      font-size:0.88rem;
    }
    thead tr { background:#1a1a1a; }
    th {
      padding:0.85rem 1rem; text-align:left;
      font-size:0.68rem; font-weight:700;
      letter-spacing:0.12em; text-transform:uppercase;
      color:rgba(250,246,239,0.4);
      white-space:nowrap;
      border-bottom:1px solid rgba(255,255,255,0.05);
    }
    td {
      padding:0.9rem 1rem;
      border-bottom:1px solid rgba(255,255,255,0.05);
      vertical-align:top;
    }
    tr:hover td { background:rgba(255,255,255,0.02); }

    .badge {
      display:inline-block;
      font-size:0.65rem; font-weight:700;
      letter-spacing:0.1em; text-transform:uppercase;
      padding:0.25rem 0.65rem;
    }
    .badge-attente { background:rgba(201,168,76,0.15); color:var(--or); }
    .badge-valide  { background:rgba(45,122,79,0.15);  color:#6fcf97; }

    .td-nom strong { font-weight:700; }
    .td-nom small  { display:block; font-size:0.78rem; color:rgba(250,246,239,0.4); }

    .btn-sm {
      display:inline-block; font-size:0.7rem; font-weight:700;
      letter-spacing:0.1em; text-transform:uppercase;
      padding:0.35rem 0.8rem; text-decoration:none;
      border:1px solid transparent; cursor:pointer;
      font-family:'Lato',sans-serif;
      transition:all 0.2s; white-space:nowrap;
    }
    .btn-valider {
      background:var(--rouge); border-color:var(--rouge); color:var(--creme);
    }
    .btn-valider:hover { background:var(--rouge-clair); border-color:var(--rouge-clair); }
    .btn-dl {
      background:transparent; border-color:rgba(201,168,76,0.4); color:var(--or);
    }
    .btn-dl:hover { background:rgba(201,168,76,0.1); }
    .no-doc { font-size:0.75rem; color:rgba(250,246,239,0.2); font-style:italic; }

    .empty {
      text-align:center; padding:3rem;
      font-size:0.9rem; color:rgba(250,246,239,0.3);
    }

    /* ── RESPONSIVE ──────── */
    @media(max-width:700px) {
      .topbar { padding:1rem 1.5rem; }
      .stats { grid-template-columns:1fr; }
      .page { padding:1.5rem 1rem; }
    }
  </style>
</head>
<body>

<!-- ══════ TOP BAR ══════ -->
<div class="topbar">
  <div class="topbar-brand">
    Flor de Tango
    <span>Administration</span>
  </div>
  <div class="topbar-links">
    <a href="index.html">← Site</a>
    <a href="admin.php?logout=1" class="logout">Déconnexion</a>
  </div>
</div>

<!-- ══════ PAGE ══════ -->
<div class="page">

  <?php if ($flash): ?>
    <div class="flash"><?= h($flash) ?></div>
  <?php endif; ?>

  <!-- Stats -->
  <div class="stats">
    <div class="stat-card">
      <div class="stat-num"><?= $total ?></div>
      <div class="stat-label">Inscriptions totales</div>
    </div>
    <div class="stat-card rouge">
      <div class="stat-num"><?= $enAttente ?></div>
      <div class="stat-label">En attente</div>
    </div>
    <div class="stat-card vert">
      <div class="stat-num"><?= $valide ?></div>
      <div class="stat-label">Validées</div>
    </div>
  </div>

  <!-- Filters -->
  <div class="filter-tabs">
    <a href="admin.php?statut=tous"       class="<?= $filtre === 'tous'       ? 'active' : '' ?>">Toutes (<?= $total ?>)</a>
    <a href="admin.php?statut=en_attente" class="<?= $filtre === 'en_attente' ? 'active' : '' ?>">En attente (<?= $enAttente ?>)</a>
    <a href="admin.php?statut=valide"     class="<?= $filtre === 'valide'     ? 'active' : '' ?>">Validées (<?= $valide ?>)</a>
  </div>

  <!-- Table -->
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Personne</th>
          <th>Téléphone</th>
          <th>Cours</th>
          <th>Statut</th>
          <th>Date</th>
          <th>Document</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="8" class="empty">Aucune inscription trouvée.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td style="color:rgba(250,246,239,0.3)"><?= $r['id'] ?></td>
              <td class="td-nom">
                <strong><?= h($r['nom']) ?></strong>
                <small><?= h($r['email']) ?></small>
              </td>
              <td><?= h($r['telephone'] ?: '—') ?></td>
              <td style="max-width:200px;line-height:1.4"><?= h($r['cours']) ?></td>
              <td>
                <?php if ($r['statut'] === 'en_attente'): ?>
                  <span class="badge badge-attente">En attente</span>
                <?php else: ?>
                  <span class="badge badge-valide">Validée</span>
                <?php endif; ?>
              </td>
              <td style="white-space:nowrap;color:rgba(250,246,239,0.5)">
                <?= date('d/m/Y H:i', strtotime($r['cree_le'])) ?>
              </td>
              <td>
                <?php if ($r['fichier']): ?>
                  <a href="download.php?id=<?= $r['id'] ?>" class="btn-sm btn-dl" title="<?= h($r['fichier_nom']) ?>">
                    Télécharger
                  </a>
                <?php else: ?>
                  <span class="no-doc">Aucun fichier</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($r['statut'] === 'en_attente'): ?>
                  <form method="POST" onsubmit="return confirm('Valider l\'inscription de <?= h(addslashes($r['nom'])) ?> et lui envoyer un e-mail de confirmation ?')">
                    <input type="hidden" name="action" value="valider" />
                    <input type="hidden" name="id"     value="<?= $r['id'] ?>" />
                    <button type="submit" class="btn-sm btn-valider">Valider</button>
                  </form>
                <?php else: ?>
                  <span style="font-size:0.75rem;color:rgba(250,246,239,0.2)">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

</div><!-- /page -->
</body>
</html>
