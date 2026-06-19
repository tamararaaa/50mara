<?php
require_once __DIR__ . '/config.php';

$password = 'mara2026';
$auth = false;

if (isset($_POST['pass']) && $_POST['pass'] === $password) {
    setcookie('admin_auth', md5($password), time() + 3600);
    $auth = true;
} elseif (isset($_COOKIE['admin_auth']) && $_COOKIE['admin_auth'] === md5($password)) {
    $auth = true;
}

if (isset($_GET['logout'])) {
    setcookie('admin_auth', '', time() - 3600);
    header('Location: admin.php');
    exit;
}

if (!$auth) { ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin – Mara 50</title>
<style>
  body { font-family: 'Lato', sans-serif; background: #f5f7fc; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
  .login { background: white; border-radius: 16px; padding: 40px; box-shadow: 0 4px 24px rgba(30,70,140,0.1); text-align: center; width: 300px; }
  h2 { color: #1a3a6b; margin-bottom: 24px; font-size: 20px; font-weight: 400; letter-spacing: 2px; }
  input { width: 100%; padding: 10px 14px; border: 1.5px solid #c8daf5; border-radius: 10px; font-size: 14px; margin-bottom: 14px; box-sizing: border-box; outline: none; }
  button { width: 100%; padding: 12px; background: #2d5a9e; color: white; border: none; border-radius: 10px; font-size: 14px; cursor: pointer; }
  button:hover { background: #1a3a6b; }
</style>
</head>
<body>
<div class="login">
  <h2>Panel Admin — Mara</h2>
  <form method="POST">
    <input type="password" name="pass" placeholder="Contraseña"/>
    <button type="submit">Ingresar</button>
  </form>
</div>
</body>
</html>
<?php exit; }

// ---- ACCIONES AJAX ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    $body = json_decode(file_get_contents('php://input'), true);
    $body = $body ?? [];
    $action = $_GET['action'];

    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        if ($action === 'asignar_mesa') {
            $pdo->prepare("UPDATE confirmaciones SET mesa = ? WHERE invitado_id = ?")
                ->execute([$body['mesa'], $body['invitado_id']]);
            echo json_encode(['ok' => true]);
        }

        elseif ($action === 'editar_invitado') {
            $pdo->prepare("UPDATE invitados SET nombre = ?, email = ?, telefono = ?, codigo = ? WHERE id = ?")
                ->execute([$body['nombre'], $body['email'], $body['telefono'], $body['codigo'], $body['id']]);
            echo json_encode(['ok' => true]);
        }

        elseif ($action === 'agregar_invitado') {
            $codigo = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $body['nombre']), 0, 3)) . rand(10, 99);
            $es_individual = (int)($body['es_individual'] ?? 0);
            $pdo->prepare("INSERT INTO invitados (grupo_id, nombre, codigo, email, telefono, es_individual) VALUES (?, ?, ?, ?, ?, ?)")
                ->execute([$body['grupo_id'], $body['nombre'], $codigo, $body['email'] ?? '', $body['telefono'] ?? '', $es_individual]);
            echo json_encode(['ok' => true, 'codigo' => $codigo]);
        }

        elseif ($action === 'eliminar_invitado') {
            $pdo->prepare("DELETE FROM confirmaciones WHERE invitado_id = ?")->execute([$body['id']]);
            $pdo->prepare("DELETE FROM invitados WHERE id = ?")->execute([$body['id']]);
            echo json_encode(['ok' => true]);
        }

        elseif ($action === 'eliminar_no_asistente') {
            $pdo->prepare("DELETE FROM no_asistentes WHERE id = ?")->execute([$body['id']]);
            echo json_encode(['ok' => true]);
        }

        elseif ($action === 'contadores') {
            $confirmados = $pdo->query("SELECT COUNT(*) FROM confirmaciones WHERE asiste = 1")->fetchColumn();
            $no_asisten = $pdo->query("SELECT COUNT(*) FROM no_asistentes")->fetchColumn();
            $sin_responder = $pdo->query("SELECT COUNT(*) FROM invitados WHERE respondio = 0")->fetchColumn();
            echo json_encode(['ok' => true, 'confirmados' => (int)$confirmados, 'no_asisten' => (int)$no_asisten, 'sin_responder' => (int)$sin_responder]);
        }

        else {
            echo json_encode(['ok' => false, 'error' => 'Accion desconocida']);
        }

    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ---- CARGAR DATOS ----
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $confirmados = $pdo->query("
        SELECT i.id, i.nombre, i.codigo, i.email, i.telefono, g.nombre_grupo,
               c.restriccion_dieta, c.detalle_dieta, c.transporte, c.mesa, c.respondido_en
        FROM invitados i
        JOIN grupos g ON i.grupo_id = g.id
        JOIN confirmaciones c ON c.invitado_id = i.id
        WHERE c.asiste = 1
        ORDER BY g.nombre_grupo, i.nombre
    ")->fetchAll(PDO::FETCH_ASSOC);

    $no_asistentes = $pdo->query("
        SELECT * FROM no_asistentes ORDER BY respondido_en DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $sin_responder = $pdo->query("
        SELECT i.id, i.nombre, i.codigo, i.email, i.telefono, g.nombre_grupo
        FROM invitados i
        JOIN grupos g ON i.grupo_id = g.id
        WHERE i.respondio = 0
        ORDER BY g.nombre_grupo, i.nombre
    ")->fetchAll(PDO::FETCH_ASSOC);

    $grupos = $pdo->query("SELECT id, nombre_grupo FROM grupos ORDER BY nombre_grupo")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die('Error de base de datos: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin – Mara 50</title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: 'Lato', sans-serif; background: #f5f7fc; color: #1a2a4a; }
header { background: #1a3a6b; color: white; padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; }
header h1 { font-size: 16px; font-weight: 400; letter-spacing: 2px; }
header a { color: #c8daf5; font-size: 13px; text-decoration: none; }
.tabs { display: flex; gap: 0; background: white; border-bottom: 2px solid #eaf2fc; padding: 0 24px; overflow-x: auto; }
.tab { padding: 14px 20px; cursor: pointer; font-size: 13px; letter-spacing: 1px; color: #5b8ecf; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: all .2s; white-space: nowrap; }
.tab.active { color: #1a3a6b; border-bottom-color: #2d5a9e; font-weight: 700; }
.tab-content { display: none; padding: 24px; }
.tab-content.active { display: block; }
.stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 24px; }
.stat { background: white; border-radius: 12px; padding: 16px 20px; box-shadow: 0 2px 12px rgba(30,70,140,0.07); }
.stat .num { font-size: 32px; font-weight: 300; color: #1a3a6b; }
.stat .lbl { font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: #5b8ecf; margin-bottom: 4px; }
table { width: 100%; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 12px rgba(30,70,140,0.07); border-collapse: collapse; font-size: 13px; }
th { background: #eaf2fc; color: #2d5a9e; font-size: 11px; letter-spacing: 1.5px; text-transform: uppercase; padding: 10px 14px; text-align: left; font-weight: 400; }
td { padding: 10px 14px; border-bottom: 1px solid #eaf2fc; vertical-align: middle; }
tr:last-child td { border-bottom: none; }
tr:hover td { background: #f8faff; }
.code { background: #2d5a9e; color: white; padding: 2px 8px; border-radius: 6px; font-size: 11px; letter-spacing: 2px; font-weight: 700; }
.badge { padding: 2px 8px; border-radius: 6px; font-size: 11px; }
.badge-diet { background: #eaf2fc; color: #2d5a9e; }
.badge-transport { background: #e8f5ee; color: #1a6b3a; }
.empty { color: #aab5cc; font-style: italic; font-size: 12px; }
.btn { padding: 6px 14px; border-radius: 8px; border: none; cursor: pointer; font-size: 12px; font-family: 'Lato', sans-serif; transition: all .15s; }
.btn-edit { background: #eaf2fc; color: #2d5a9e; }
.btn-edit:hover { background: #c8daf5; }
.btn-del { background: #fdecea; color: #c0392b; }
.btn-del:hover { background: #f5c6c2; }
.btn-save { background: #2d5a9e; color: white; }
.btn-save:hover { background: #1a3a6b; }
.btn-add { background: #2d5a9e; color: white; padding: 10px 20px; border-radius: 10px; border: none; cursor: pointer; font-size: 13px; font-family: 'Lato', sans-serif; margin-bottom: 16px; }
.btn-add:hover { background: #1a3a6b; }
input.inline { border: 1.5px solid #c8daf5; border-radius: 6px; padding: 4px 8px; font-size: 12px; font-family: 'Lato', sans-serif; outline: none; width: 100%; }
input.inline:focus { border-color: #2d5a9e; }
select.inline { border: 1.5px solid #c8daf5; border-radius: 6px; padding: 4px 8px; font-size: 12px; font-family: 'Lato', sans-serif; outline: none; }
.section-title { font-size: 12px; letter-spacing: 2px; text-transform: uppercase; color: #5b8ecf; margin-bottom: 14px; font-weight: 400; }
.mesa-input { width: 90px; }
.form-field-label { font-size: 11px; letter-spacing: 1.5px; text-transform: uppercase; color: #5b8ecf; display: block; margin-bottom: 5px; }
.form-field-input { width: 100%; padding: 9px 12px; border: 1.5px solid #c8daf5; border-radius: 8px; font-size: 14px; font-family: 'Lato', sans-serif; outline: none; box-sizing: border-box; }
.form-field-input:focus { border-color: #2d5a9e; }
.form-field { margin-bottom: 14px; }
.checkbox-row { display: flex; align-items: center; gap: 8px; font-size: 14px; cursor: pointer; }
.checkbox-row input[type="checkbox"] { width: 16px; height: 16px; accent-color: #2d5a9e; cursor: pointer; }
@media (max-width: 600px) {
  .stats { grid-template-columns: 1fr 1fr; }
  .tab-content { padding: 16px; }
  th, td { padding: 8px 10px; }
}
</style>
</head>
<body>

<header>
  <h1>Panel Admin — Mara</h1>
  <a href="?logout=1">Cerrar sesion</a>
</header>

<div class="tabs">
  <div class="tab active" onclick="showTab('confirmados')">Confirmados</div>
  <div class="tab" onclick="showTab('no_asisten')">No asisten</div>
  <div class="tab" onclick="showTab('sin_responder')">Sin responder</div>
  <div class="tab" onclick="showTab('agregar')">Agregar invitado</div>
</div>

<!-- TAB CONFIRMADOS -->
<div class="tab-content active" id="tab-confirmados">
  <div class="stats">
    <div class="stat"><p class="lbl">Confirmados</p><p class="num" id="num-confirmados"><?= count($confirmados) ?></p></div>
    <div class="stat"><p class="lbl">No asisten</p><p class="num" id="num-no-asisten"><?= count($no_asistentes) ?></p></div>
    <div class="stat"><p class="lbl">Sin responder</p><p class="num" id="num-sin-responder"><?= count($sin_responder) ?></p></div>
  </div>
  <p class="section-title">Asistentes confirmados</p>
  <table>
    <tr>
      <th>Nombre</th>
      <th>Grupo</th>
      <th>Codigo</th>
      <th>Email</th>
      <th>Telefono</th>
      <th>Dieta</th>
      <th>Transporte</th>
      <th>Mesa</th>
      <th>Acciones</th>
    </tr>
    <?php if (empty($confirmados)): ?>
    <tr><td colspan="9" class="empty" style="text-align:center;padding:20px;">Sin confirmaciones aun</td></tr>
    <?php else: foreach ($confirmados as $c): ?>
    <tr id="row-<?= $c['id'] ?>">
      <td>
        <span class="view-mode"><?= htmlspecialchars($c['nombre']) ?></span>
        <input class="inline edit-mode" style="display:none;" data-field="nombre" value="<?= htmlspecialchars($c['nombre']) ?>"/>
      </td>
      <td><?= htmlspecialchars($c['nombre_grupo']) ?></td>
      <td>
        <span class="view-mode"><span class="code"><?= htmlspecialchars($c['codigo']) ?></span></span>
        <input class="inline edit-mode" style="display:none;" data-field="codigo" value="<?= htmlspecialchars($c['codigo']) ?>"/>
      </td>
      <td>
        <span class="view-mode"><?= htmlspecialchars($c['email'] ?? '—') ?></span>
        <input class="inline edit-mode" style="display:none;" data-field="email" value="<?= htmlspecialchars($c['email'] ?? '') ?>"/>
      </td>
      <td>
        <span class="view-mode"><?= htmlspecialchars($c['telefono'] ?? '—') ?></span>
        <input class="inline edit-mode" style="display:none;" data-field="telefono" value="<?= htmlspecialchars($c['telefono'] ?? '') ?>" oninput="this.value=this.value.replace(/[^0-9\s\-\+]/g,'')"/>
      </td>
      <td>
        <?php if ($c['restriccion_dieta']): ?>
          <span class="badge badge-diet"><?= htmlspecialchars($c['restriccion_dieta']) ?></span>
          <?php if ($c['detalle_dieta']): ?><br><small><?= htmlspecialchars($c['detalle_dieta']) ?></small><?php endif; ?>
        <?php else: ?><span class="empty">—</span><?php endif; ?>
      </td>
      <td>
        <?php if ($c['transporte'] && $c['transporte'] !== 'ninguna'): ?>
          <span class="badge badge-transport"><?= htmlspecialchars($c['transporte']) ?></span>
        <?php else: ?><span class="empty">No necesita</span><?php endif; ?>
      </td>
      <td>
        <span class="view-mode"><?= $c['mesa'] ? htmlspecialchars($c['mesa']) : '<span class="empty">—</span>' ?></span>
        <input class="inline edit-mode mesa-input" type="number" style="display:none;" data-field="mesa" value="<?= htmlspecialchars($c['mesa'] ?? '') ?>" placeholder="Nro"/>
      </td>
      <td style="white-space:nowrap;">
        <button class="btn btn-edit view-mode" onclick="editRow(<?= $c['id'] ?>)">Editar</button>
        <button class="btn btn-save edit-mode" style="display:none;" onclick="saveRow(<?= $c['id'] ?>)">Guardar</button>
        <button class="btn btn-del" onclick="eliminarInvitado(<?= $c['id'] ?>, '<?= htmlspecialchars($c['nombre']) ?>')">Eliminar</button>
      </td>
    </tr>
    <?php endforeach; endif; ?>
  </table>
</div>

<!-- TAB NO ASISTEN -->
<div class="tab-content" id="tab-no_asisten">
  <p class="section-title">No pueden asistir</p>
  <table>
    <tr>
      <th>Nombre</th>
      <th>Mensaje para Mara</th>
      <th>Fecha</th>
      <th>Acciones</th>
    </tr>
    <?php if (empty($no_asistentes)): ?>
    <tr><td colspan="4" class="empty" style="text-align:center;padding:20px;">Sin registros</td></tr>
    <?php else: foreach ($no_asistentes as $n): ?>
    <tr>
      <td><?= $n['nombre'] ? htmlspecialchars($n['nombre']) : '<span class="empty">Anonimo</span>' ?></td>
      <td><?= $n['mensaje'] ? htmlspecialchars($n['mensaje']) : '<span class="empty">Sin mensaje</span>' ?></td>
      <td><?= date('d/m H:i', strtotime($n['respondido_en'])) ?></td>
      <td><button class="btn btn-del" onclick="eliminarNoAsistente(<?= $n['id'] ?>)">Eliminar</button></td>
    </tr>
    <?php endforeach; endif; ?>
  </table>
</div>

<!-- TAB SIN RESPONDER -->
<div class="tab-content" id="tab-sin_responder">
  <p class="section-title">Todavia no respondieron</p>
  <table>
    <tr>
      <th>Nombre</th>
      <th>Grupo</th>
      <th>Codigo</th>
      <th>WhatsApp</th>
      <th>Acciones</th>
    </tr>
    <?php if (empty($sin_responder)): ?>
    <tr><td colspan="5" class="empty" style="text-align:center;padding:20px;">Todos respondieron</td></tr>
    <?php else: foreach ($sin_responder as $s): ?>
    <tr id="row-sr-<?= $s['id'] ?>">
      <td><?= htmlspecialchars($s['nombre']) ?></td>
      <td><?= htmlspecialchars($s['nombre_grupo']) ?></td>
      <td><span class="code"><?= htmlspecialchars($s['codigo']) ?></span></td>
      <td>
        <a href="https://wa.me/?text=<?= urlencode('Hola ' . $s['nombre'] . '! Te comparto tu invitacion para el cumpleaños de Mara! Tu codigo de acceso es: ' . $s['codigo'] . '. Confirma tu asistencia aca: http://192.168.1.53/mara50/?c=' . $s['codigo']) ?>" target="_blank" class="btn btn-save" style="text-decoration:none;">WhatsApp</a>
      </td>
      <td><button class="btn btn-del" onclick="eliminarInvitado(<?= $s['id'] ?>, '<?= htmlspecialchars($s['nombre']) ?>')">Eliminar</button></td>
    </tr>
    <?php endforeach; endif; ?>
  </table>
</div>

<!-- TAB AGREGAR -->
<div class="tab-content" id="tab-agregar">
  <p class="section-title">Agregar nuevo invitado</p>
  <div style="background:white;border-radius:12px;padding:24px;max-width:420px;box-shadow:0 2px 12px rgba(30,70,140,0.07);">
    <div class="form-field">
      <label class="form-field-label">Nombre completo</label>
      <input type="text" id="new-nombre" class="form-field-input" placeholder="Nombre y apellido"/>
    </div>
    <div class="form-field" id="grupo-field">
      <label class="form-field-label">Grupo familiar</label>
      <select id="new-grupo" class="form-field-input">
        <?php foreach ($grupos as $g): if ($g['nombre_grupo'] === 'Individuales') continue; ?>
        <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['nombre_grupo']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-field">
      <label class="checkbox-row">
        <input type="checkbox" id="new-individual" onchange="toggleIndividual()"/>
        Es invitado individual (sin grupo familiar)
      </label>
    </div>
    <div class="form-field">
      <label class="form-field-label">Mail (opcional)</label>
      <input type="email" id="new-email" class="form-field-input" placeholder="tu@mail.com"/>
    </div>
    <div class="form-field">
      <label class="form-field-label">Telefono (opcional)</label>
      <input type="tel" id="new-tel" class="form-field-input" placeholder="11 1234-5678" oninput="this.value=this.value.replace(/[^0-9\s\-\+]/g,'')"/>
    </div>
    <button class="btn-add" onclick="agregarInvitado()">Agregar invitado</button>
    <p id="add-result" style="font-size:13px;margin-top:10px;display:none;"></p>
  </div>
</div>

<script>
function showTab(name) {
  document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
  document.querySelector('.tab[onclick="showTab(\'' + name + '\')"]').classList.add('active');
  document.getElementById('tab-' + name).classList.add('active');
}

function toggleIndividual() {
  const isIndividual = document.getElementById('new-individual').checked;
  document.getElementById('grupo-field').style.display = isIndividual ? 'none' : 'block';
}

function editRow(id) {
  const row = document.getElementById('row-' + id);
  row.querySelectorAll('.view-mode').forEach(el => el.style.display = 'none');
  row.querySelectorAll('.edit-mode').forEach(el => el.style.display = '');
}

async function saveRow(id) {
  const row = document.getElementById('row-' + id);
  const nombre = row.querySelector('[data-field="nombre"]').value;
  const mesa = row.querySelector('[data-field="mesa"]').value;
  const codigo = row.querySelector('[data-field="codigo"]').value;
  const email = row.querySelector('[data-field="email"]').value;
  const telefono = row.querySelector('[data-field="telefono"]').value;

  await fetch('admin.php?action=asignar_mesa', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ invitado_id: id, mesa: mesa })
  });
  await fetch('admin.php?action=editar_invitado', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ id: id, nombre: nombre, email: email, telefono: telefono, codigo: codigo })
  });

  row.querySelectorAll('.view-mode').forEach(el => el.style.display = '');
  row.querySelectorAll('.edit-mode').forEach(el => el.style.display = 'none');
  location.reload();
}

async function eliminarInvitado(id, nombre) {
  if (!confirm('Eliminar a ' + nombre + '?')) return;
  await fetch('admin.php?action=eliminar_invitado', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ id: id })
  });
  const row = document.getElementById('row-' + id) || document.getElementById('row-sr-' + id);
  if (row) row.remove();
}

async function eliminarNoAsistente(id) {
  if (!confirm('Eliminar este registro?')) return;
  await fetch('admin.php?action=eliminar_no_asistente', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ id: id })
  });
  location.reload();
}

async function agregarInvitado() {
  const nombre = document.getElementById('new-nombre').value.trim();
  const es_individual = document.getElementById('new-individual').checked ? 1 : 0;
  const grupo_id = es_individual ? 36 : document.getElementById('new-grupo').value;
  const email = document.getElementById('new-email').value.trim();
  const telefono = document.getElementById('new-tel').value.trim();
  const result = document.getElementById('add-result');

  if (!nombre) {
    result.textContent = 'El nombre es obligatorio.';
    result.style.color = '#c0392b';
    result.style.display = 'block';
    return;
  }

  const res = await fetch('admin.php?action=agregar_invitado', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ nombre, grupo_id, email, telefono, es_individual })
  });
  const data = await res.json();

  if (data.ok) {
    result.textContent = 'Invitado agregado. Codigo: ' + data.codigo;
    result.style.color = '#1a6b3a';
    result.style.display = 'block';
    document.getElementById('new-nombre').value = '';
    document.getElementById('new-email').value = '';
    document.getElementById('new-tel').value = '';
    document.getElementById('new-individual').checked = false;
    toggleIndividual();
  } else {
    result.textContent = 'Error al agregar.';
    result.style.color = '#c0392b';
    result.style.display = 'block';
  }
}

// Restaurar pestaña activa despues de reload
const tabGuardada = sessionStorage.getItem('tabActiva');
if (tabGuardada) {
  showTab(tabGuardada);
  sessionStorage.removeItem('tabActiva');
}

let contadoresAnteriores = {
  confirmados: <?= count($confirmados) ?>,
  no_asisten: <?= count($no_asistentes) ?>,
  sin_responder: <?= count($sin_responder) ?>
};

setInterval(async () => {
  try {
    const res = await fetch('admin.php?action=contadores', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({})
    });
    const data = await res.json();
    if (!data.ok) return;
    if (
      data.confirmados !== contadoresAnteriores.confirmados ||
      data.no_asisten !== contadoresAnteriores.no_asisten ||
      data.sin_responder !== contadoresAnteriores.sin_responder
    ) {
      const tabActiva = document.querySelector('.tab.active')?.getAttribute('onclick');
      const match = tabActiva?.match(/showTab\('(.+?)'\)/);
      if (match) sessionStorage.setItem('tabActiva', match[1]);
      location.reload();
    }
  } catch(e) {}
}, 10000);
</script>
</body>
</html>