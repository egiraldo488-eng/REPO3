<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/config.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];
$error = '';
$notice = $_SESSION['notice'] ?? '';
unset($_SESSION['notice']);

function h(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function redirectHome(): never {
    header('Location: index.php');
    exit;
}

try {
    $pdo = db();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = (string)($_POST['csrf_token'] ?? '');
        if (!hash_equals($_SESSION['csrf_token'], $token)) {
            throw new RuntimeException('La sesión del formulario expiró. Recarga la página e inténtalo de nuevo.');
        }

        $action = (string)($_POST['action'] ?? '');
        if ($action === 'save') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;
            $destinatario = trim((string)($_POST['destinatario'] ?? ''));
            $direccion = trim((string)($_POST['direccion'] ?? ''));
            $descripcion = trim((string)($_POST['descripcion'] ?? ''));

            if ($destinatario === '' || $direccion === '' || $descripcion === '') {
                throw new RuntimeException('Completa todos los campos antes de guardar.');
            }
            if (mb_strlen($destinatario) > 150 || mb_strlen($direccion) > 255) {
                throw new RuntimeException('El destinatario o la dirección superan la longitud permitida.');
            }

            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE envios SET destinatario = ?, direccion = ?, descripcion = ? WHERE id = ?');
                $stmt->execute([$destinatario, $direccion, $descripcion, $id]);
                $_SESSION['notice'] = 'Envío actualizado correctamente.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO envios (destinatario, direccion, descripcion) VALUES (?, ?, ?)');
                $stmt->execute([$destinatario, $direccion, $descripcion]);
                $_SESSION['notice'] = 'Envío registrado correctamente.';
            }
            redirectHome();
        }

        if ($action === 'delete') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if (!$id) {
                throw new RuntimeException('No se recibió un envío válido.');
            }
            $stmt = $pdo->prepare('DELETE FROM envios WHERE id = ?');
            $stmt->execute([$id]);
            $_SESSION['notice'] = 'Envío eliminado.';
            redirectHome();
        }
    }

    $edit = null;
    if (isset($_GET['editar'])) {
        $id = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT);
        if ($id) {
            $stmt = $pdo->prepare('SELECT * FROM envios WHERE id = ?');
            $stmt->execute([$id]);
            $edit = $stmt->fetch() ?: null;
        }
    }

    $search = trim((string)($_GET['buscar'] ?? ''));
    if ($search !== '') {
        $stmt = $pdo->prepare("SELECT * FROM envios WHERE destinatario LIKE ? OR direccion LIKE ? OR descripcion LIKE ? ORDER BY creado_en DESC, id DESC");
        $like = '%' . $search . '%';
        $stmt->execute([$like, $like, $like]);
        $envios = $stmt->fetchAll();
    } else {
        $envios = $pdo->query('SELECT * FROM envios ORDER BY creado_en DESC, id DESC')->fetchAll();
    }
    $total = (int)$pdo->query('SELECT COUNT(*) FROM envios')->fetchColumn();
} catch (Throwable $e) {
    if ($e instanceof RuntimeException && !($e instanceof PDOException)) {
        $error = $e->getMessage();
    } else {
        $error = 'No se pudo conectar o consultar la base de datos. Verifica las credenciales y que el servidor permita conexiones MySQL.';
    }
    $envios = $envios ?? [];
    $edit = $edit ?? null;
    $total = $total ?? 0;
    $search = $search ?? '';
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ébano | Gestión de envíos</title>
    <style>
        :root{--bg:#0b0b0d;--panel:#141416;--panel2:#1b1b1f;--gold:#d6b15e;--gold2:#f0d58b;--text:#f5f1e8;--muted:#a6a29a;--line:#302b20;--danger:#ff7777}
        *{box-sizing:border-box}body{margin:0;background:radial-gradient(ellipse at top right,#282113 0,transparent 38%),var(--bg);color:var(--text);font:15px/1.55 Inter,Segoe UI,Arial,sans-serif;min-height:100vh}
        .wrap{width:min(1180px,calc(100% - 36px));margin:0 auto;padding:34px 0 48px}
        header{display:flex;align-items:center;justify-content:space-between;gap:18px;margin-bottom:30px}
        .brand{display:flex;align-items:center;gap:14px}.mark{width:48px;height:48px;border:1px solid var(--gold);border-radius:14px;display:grid;place-items:center;color:var(--gold2);font-weight:800;font-size:22px;box-shadow:0 0 24px #d6b15e12}
        .eyebrow{font-size:11px;letter-spacing:.22em;text-transform:uppercase;color:var(--gold);font-weight:700}.brand h1{font-size:22px;letter-spacing:.04em;margin:1px 0 0}.sub{color:var(--muted);font-size:13px}
        .pill{border:1px solid var(--line);border-radius:999px;padding:9px 14px;color:var(--muted);font-size:12px}.pill b{color:var(--gold2)}
        .hero{display:flex;justify-content:space-between;align-items:end;gap:22px;margin-bottom:24px}.hero h2{font-size:clamp(26px,4vw,38px);line-height:1.12;margin:7px 0 10px;letter-spacing:-.035em}.hero p{margin:0;color:var(--muted);max-width:550px}
        .stats{display:flex;gap:12px;flex-wrap:wrap}.stat{background:linear-gradient(145deg,#191919,#111113);border:1px solid var(--line);border-radius:16px;padding:16px 20px;min-width:150px}.stat small{display:block;color:var(--muted);font-size:12px}.stat strong{display:block;font-size:27px;color:var(--gold2);margin-top:3px}
        .layout{display:grid;grid-template-columns:minmax(280px,350px) minmax(0,1fr);gap:20px;align-items:start}.panel{background:linear-gradient(150deg,#171719,#111113);border:1px solid #2b2923;border-radius:20px;overflow:hidden;box-shadow:0 18px 60px #0003}.panel-head{padding:21px 22px;border-bottom:1px solid #292720}.panel-head h3{margin:0;font-size:16px}.panel-head p{margin:5px 0 0;color:var(--muted);font-size:12px}.panel-body{padding:22px}
        label{display:block;color:#d9d4c8;font-size:12px;font-weight:650;margin:0 0 7px}.field{margin-bottom:17px}input,textarea{width:100%;background:#0d0d0f;border:1px solid #34312a;border-radius:11px;padding:12px 13px;color:var(--text);font:inherit;outline:none;transition:.2s}input:focus,textarea:focus{border-color:var(--gold);box-shadow:0 0 0 3px #d6b15e17}textarea{resize:vertical;min-height:108px}input::placeholder,textarea::placeholder{color:#68665f}
        .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border:0;border-radius:11px;padding:11px 16px;font-weight:750;font-size:13px;text-decoration:none;cursor:pointer;transition:transform .15s,filter .15s}.btn:hover{filter:brightness(1.08);transform:translateY(-1px)}.primary{background:linear-gradient(135deg,var(--gold2),#bc913a);color:#17130a;width:100%}.secondary{background:#24231f;color:var(--gold2);border:1px solid #4a4028}.danger{background:#2b1717;color:#ffaaaa;border:1px solid #512727}.btn-small{padding:7px 10px;font-size:12px;border-radius:8px}
        .toolbar{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:17px 20px;border-bottom:1px solid #292720}.toolbar h3{margin:0;font-size:16px}.search{display:flex;gap:8px;max-width:350px;width:100%}.search input{padding:9px 12px;font-size:13px}.search .btn{padding:8px 12px}
        .table-wrap{overflow-x:auto}table{width:100%;border-collapse:collapse;min-width:590px}th{text-align:left;font-size:10px;letter-spacing:.13em;text-transform:uppercase;color:#9f9785;padding:14px 20px;background:#111113}td{padding:16px 20px;border-top:1px solid #26251f;vertical-align:top;font-size:13px}tbody tr:hover{background:#ffffff03}.recipient{font-weight:700;color:#f3e8ca}.address{color:#bdb8ad;max-width:220px}.description{color:#aaa69c;max-width:250px;white-space:pre-wrap;overflow-wrap:anywhere}.id{color:var(--gold);font-size:11px}.actions{display:flex;gap:7px;align-items:center}.actions form{margin:0}.empty{text-align:center;padding:48px 20px;color:var(--muted)}.empty .symbol{font-size:30px;color:var(--gold);margin-bottom:8px}
        .alert{padding:12px 15px;border-radius:12px;margin-bottom:18px;font-size:13px}.alert.ok{background:#172219;border:1px solid #2e5738;color:#bce7c4}.alert.err{background:#2a1717;border:1px solid #643030;color:#ffc0c0}.hint{color:#77736b;font-size:11px;margin-top:12px}.footer{display:flex;justify-content:space-between;gap:12px;margin-top:22px;color:#77736b;font-size:11px}
        @media(max-width:850px){.layout{grid-template-columns:1fr}.hero{align-items:start;flex-direction:column}.stats{width:100%}.stat{flex:1}.toolbar{align-items:flex-start;flex-direction:column}.search{max-width:none}header{align-items:flex-start;flex-direction:column}.pill{align-self:flex-start}}
    </style>
</head>
<body>
<div class="wrap">
    <header>
        <div class="brand">
            <div class="mark">É</div>
            <div><div class="eyebrow">Control logístico</div><h1>ÉBANO <span style="color:var(--gold);font-weight:400">/ ENVIOS</span></h1></div>
        </div>
        <div class="pill"><b>●</b> Panel de administración</div>
    </header>

    <section class="hero">
        <div>
            <div class="eyebrow">Centro de operaciones</div>
            <h2>Gestión de envíos<br><span style="color:var(--gold2)">con precisión.</span></h2>
            <p>Registra, consulta y administra la información de cada entrega desde un solo lugar.</p>
        </div>
        <div class="stats"><div class="stat"><small>Envíos registrados</small><strong><?= (int)$total ?></strong></div></div>
    </section>

    <?php if ($notice): ?><div class="alert ok"><?= h($notice) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert err"><?= h($error) ?></div><?php endif; ?>

    <main class="layout">
        <section class="panel">
            <div class="panel-head">
                <h3><?= $edit ? 'Editar envío' : 'Registrar envío' ?></h3>
                <p><?= $edit ? 'Modifica los datos y guarda los cambios.' : 'Añade los datos del destinatario y la entrega.' ?></p>
            </div>
            <div class="panel-body">
                <form method="post" action="index.php" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= h((string)($edit['id'] ?? '')) ?>">
                    <div class="field"><label for="destinatario">DESTINATARIO</label><input id="destinatario" name="destinatario" maxlength="150" required placeholder="Nombre completo" value="<?= h($edit['destinatario'] ?? '') ?>"></div>
                    <div class="field"><label for="direccion">DIRECCIÓN DE ENTREGA</label><input id="direccion" name="direccion" maxlength="255" required placeholder="Calle, número, ciudad..." value="<?= h($edit['direccion'] ?? '') ?>"></div>
                    <div class="field"><label for="descripcion">DESCRIPCIÓN DEL ENVÍO</label><textarea id="descripcion" name="descripcion" required placeholder="Contenido, indicaciones u observaciones..."><?= h($edit['descripcion'] ?? '') ?></textarea></div>
                    <button class="btn primary" type="submit"><?= $edit ? 'Guardar cambios  →' : '＋  Registrar envío' ?></button>
                    <?php if ($edit): ?><p style="text-align:center;margin:12px 0 0"><a class="sub" href="index.php">Cancelar edición</a></p><?php endif; ?>
                    <div class="hint">Los campos son obligatorios. La información se guarda en MySQL.</div>
                </form>
            </div>
        </section>

        <section class="panel">
            <div class="toolbar">
                <div><h3>Listado de envíos</h3><div class="sub"><?= count($envios) ?> resultado(s)<?= $search !== '' ? ' para “' . h($search) . '”' : '' ?></div></div>
                <form class="search" method="get" action="index.php">
                    <input name="buscar" value="<?= h($search) ?>" placeholder="Buscar envío..." aria-label="Buscar envío">
                    <button class="btn secondary" type="submit">Buscar</button>
                    <?php if ($search !== ''): ?><a class="btn secondary" href="index.php" aria-label="Limpiar búsqueda">×</a><?php endif; ?>
                </form>
            </div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>ID / Fecha</th><th>Destinatario</th><th>Dirección</th><th>Descripción</th><th>Acciones</th></tr></thead>
                    <tbody>
                    <?php if (!$envios): ?>
                        <tr><td colspan="5"><div class="empty"><div class="symbol">◇</div><strong>No hay envíos para mostrar</strong><div class="sub">Registra uno en el formulario o cambia la búsqueda.</div></div></td></tr>
                    <?php else: foreach ($envios as $envio): ?>
                        <tr>
                            <td><div class="id">#<?= (int)$envio['id'] ?></div><div class="sub"><?= h(date('d/m/Y', strtotime($envio['creado_en']))) ?></div></td>
                            <td><div class="recipient"><?= h($envio['destinatario']) ?></div></td>
                            <td><div class="address"><?= h($envio['direccion']) ?></div></td>
                            <td><div class="description"><?= h($envio['descripcion']) ?></div></td>
                            <td><div class="actions">
                                <a class="btn secondary btn-small" href="index.php?editar=<?= (int)$envio['id'] ?>">Editar</a>
                                <form method="post" onsubmit="return confirm('¿Seguro que quieres eliminar este envío?');">
                                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$envio['id'] ?>">
                                    <button class="btn danger btn-small" type="submit">Borrar</button>
                                </form>
                            </div></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
    <footer class="footer"><span>ÉBANO · Gestión de envíos</span><span>Interfaz en negro y dorado · PHP + MySQL</span></footer>
</div>
</body>
</html>
