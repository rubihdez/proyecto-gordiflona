<?php
/* =========================================================
   RECIBO DE PAGO EN PDF
   Uso: php/recibo.php?folio=GF-261102-1234
   - Solo funciona si el pedido está PAGADO
   - Solo lo puede descargar quien hizo el pedido (misma sesión) o el administrador
   No necesita librerías externas: arma el PDF directamente.
   ========================================================= */

session_start();
require_once 'conexion.php';

/* ---------- Mini generador de PDF (una página, texto y líneas) ---------- */
class PdfSimple {
    private $ops = '';
    private $alto = 842;                 // A4 en puntos (595 x 842)

    // Convierte UTF-8 a la codificación del PDF y escapa ( ) \
    private function limpiar($t) {
        $t = (string) $t;
        if (function_exists('iconv')) {
            $c = @iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $t);
            if ($c !== false) $t = $c;
        } else {
            $t = utf8_decode($t);
        }
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $t);
    }
    // y se mide desde ARRIBA de la hoja
    public function texto($x, $y, $txt, $tam = 11, $negrita = false, $rgb = [0, 0, 0]) {
        $this->ops .= sprintf("%.3F %.3F %.3F rg BT /%s %d Tf %.2F %.2F Td (%s) Tj ET\n",
            $rgb[0], $rgb[1], $rgb[2], $negrita ? 'F2' : 'F1', $tam, $x, $this->alto - $y, $this->limpiar($txt));
    }
    public function linea($x1, $y1, $x2, $y2, $grosor = 0.8, $rgb = [0, 0, 0]) {
        $this->ops .= sprintf("%.3F %.3F %.3F RG %.2F w %.2F %.2F m %.2F %.2F l S\n",
            $rgb[0], $rgb[1], $rgb[2], $grosor, $x1, $this->alto - $y1, $x2, $this->alto - $y2);
    }
    public function rectLleno($x, $y, $w, $h, $rgb) {
        $this->ops .= sprintf("%.3F %.3F %.3F rg %.2F %.2F %.2F %.2F re f\n",
            $rgb[0], $rgb[1], $rgb[2], $x, $this->alto - $y - $h, $w, $h);
    }
    public function generar() {
        $obj = [];
        $obj[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $obj[2] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
        $obj[3] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> /Contents 4 0 R >>";
        $obj[4] = "<< /Length " . strlen($this->ops) . " >>\nstream\n" . $this->ops . "endstream";
        $obj[5] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
        $obj[6] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";

        $pdf = "%PDF-1.4\n";
        $pos = [];
        foreach ($obj as $n => $contenido) {
            $pos[$n] = strlen($pdf);
            $pdf .= $n . " 0 obj\n" . $contenido . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($obj) + 1) . "\n0000000000 65535 f \n";
        foreach ($pos as $p) {
            $pdf .= sprintf("%010d 00000 n \n", $p);
        }
        $pdf .= "trailer\n<< /Size " . (count($obj) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
        return $pdf;
    }
}

function error_recibo($msg, $codigo = 403) {
    http_response_code($codigo);
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Recibo</title></head>';
    echo '<body style="font-family:sans-serif;text-align:center;padding:3rem">';
    echo '<h2>No se pudo generar el recibo</h2><p>' . htmlspecialchars($msg) . '</p>';
    echo '<a href="../proyecto.html">← Volver al inicio</a></body></html>';
    exit;
}

/* ---------- Permisos ---------- */
$folio = trim($_GET['folio'] ?? '');
$es_admin   = isset($_SESSION['admin_id']);
$es_dueno   = isset($_SESSION['pedido_actual']) && $_SESSION['pedido_actual']['folio'] === $folio;
if ($folio === '' || (!$es_admin && !$es_dueno)) {
    error_recibo('No tienes acceso a este recibo.');
}

/* ---------- Datos del pedido ---------- */
$stmt = $conexion->prepare(
    "SELECT id, folio, cliente, telefono, tipo, comentarios, total, estado, paypal_id, fecha
     FROM pedidos WHERE folio = ?"
);
$stmt->bind_param('s', $folio);
$stmt->execute();
$ped = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ped) error_recibo('El pedido no existe.', 404);
if ($ped['estado'] !== 'pagado') error_recibo('Este pedido todavía no está pagado.', 409);

$stmt = $conexion->prepare(
    "SELECT producto, guiso, cantidad, precio_unitario, subtotal FROM detalle_pedido WHERE pedido_id = ? ORDER BY id"
);
$stmt->bind_param('i', $ped['id']);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* ---------- Dibujar el recibo ---------- */
$verde = [0.047, 0.325, 0.208];     // #0C5335
$gris  = [0.35, 0.35, 0.35];
$blanco = [1, 1, 1];

function cortar($t, $n) { return mb_strlen((string) $t) > $n ? mb_substr((string) $t, 0, $n - 1) . '.' : (string) $t; }

$pdf = new PdfSimple();
$pdf->rectLleno(0, 0, 595, 90, $verde);
$pdf->texto(40, 50, 'LA GORDIFLONA', 24, true, $blanco);
$pdf->texto(40, 72, 'Gorditas, tortillones y burritos', 11, false, $blanco);
$pdf->texto(400, 50, 'RECIBO DE PAGO', 14, true, $blanco);

$y = 125;
$pdf->texto(40, $y, 'Folio:', 11, true);          $pdf->texto(120, $y, $ped['folio'], 11);
$pdf->texto(320, $y, 'Fecha:', 11, true);         $pdf->texto(370, $y, date('d/m/Y H:i', strtotime($ped['fecha'])), 11);
$y += 20;
$pdf->texto(40, $y, 'Cliente:', 11, true);        $pdf->texto(120, $y, cortar($ped['cliente'], 35), 11);
$pdf->texto(320, $y, 'Teléfono:', 11, true);      $pdf->texto(385, $y, $ped['telefono'], 11);
$y += 20;
$pdf->texto(40, $y, 'Tipo:', 11, true);           $pdf->texto(120, $y, $ped['tipo'] === 'llevar' ? 'Para llevar' : 'Comer aquí', 11);
$pdf->texto(320, $y, 'Pago:', 11, true);          $pdf->texto(370, $y, 'PayPal', 11);
$y += 20;
$pdf->texto(40, $y, 'ID transacción:', 11, true); $pdf->texto(135, $y, (string) $ped['paypal_id'], 11);

$y += 30;
$pdf->linea(40, $y, 555, $y, 1.2, $verde);
$y += 18;
$pdf->texto(40, $y, 'Producto', 11, true, $verde);
$pdf->texto(185, $y, 'Guiso', 11, true, $verde);
$pdf->texto(340, $y, 'Cant.', 11, true, $verde);
$pdf->texto(400, $y, 'P. unit.', 11, true, $verde);
$pdf->texto(485, $y, 'Subtotal', 11, true, $verde);
$y += 8;
$pdf->linea(40, $y, 555, $y, 0.6, $gris);

foreach ($items as $it) {
    $y += 20;
    $pdf->texto(40,  $y, cortar($it['producto'], 20), 11);
    $pdf->texto(185, $y, cortar($it['guiso'] !== null && $it['guiso'] !== '' ? $it['guiso'] : '-', 24), 11);
    $pdf->texto(345, $y, (string) (int) $it['cantidad'], 11);
    $pdf->texto(400, $y, '$' . number_format((float) $it['precio_unitario'], 2), 11);
    $pdf->texto(485, $y, '$' . number_format((float) $it['subtotal'], 2), 11);
}

$y += 14;
$pdf->linea(40, $y, 555, $y, 1.2, $verde);
$y += 26;
$pdf->texto(400, $y, 'TOTAL PAGADO', 12, true, $verde);
$pdf->texto(495, $y, '$' . number_format((float) $ped['total'], 2) . ' MXN', 12, true);

if (!empty($ped['comentarios'])) {
    $y += 40;
    $pdf->texto(40, $y, 'Comentarios:', 11, true);
    $pdf->texto(120, $y, cortar($ped['comentarios'], 70), 11);
}

$pdf->linea(40, 770, 555, 770, 0.6, $gris);
$pdf->texto(40, 790, '¡Gracias por tu compra! Este recibo comprueba tu pago por PayPal.', 10, false, $gris);
$pdf->texto(40, 805, 'La Gordiflona', 10, false, $gris);

$contenido = $pdf->generar();

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="recibo-' . preg_replace('/[^A-Za-z0-9-]/', '', $ped['folio']) . '.pdf"');
header('Content-Length: ' . strlen($contenido));
header('Cache-Control: private, no-store');
echo $contenido;
