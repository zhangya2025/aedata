<?php
/**
 * Minimal TCPDF-compatible shim for label printing.
 *
 * This is not a full TCPDF distribution; it implements only the
 * methods used by AEGIS_Codes::handle_print().
 */

if (!defined('ABSPATH')) {
    exit;
}

class TCPDF {
    protected $unit_scale = 72 / 25.4; // mm -> pt
    protected $page_width_pt;
    protected $page_height_pt;
    protected $pages = [];
    protected $current_page = -1;
    protected $cursor_x_pt = 0.0;
    protected $cursor_y_pt = 0.0;
    protected $font_size_pt = 10.0;

    public function __construct($orientation = 'P', $unit = 'mm', $format = [60, 30], $unicode = true, $encoding = 'UTF-8', $diskcache = false) {
        $width_mm = isset($format[0]) ? (float) $format[0] : 60.0;
        $height_mm = isset($format[1]) ? (float) $format[1] : 30.0;

        $orientation = strtoupper((string) $orientation);
        if ('L' === $orientation && $height_mm > $width_mm) {
            [$width_mm, $height_mm] = [$height_mm, $width_mm];
        }

        $this->page_width_pt = $this->mmToPt($width_mm);
        $this->page_height_pt = $this->mmToPt($height_mm);
    }

    public function SetPrintHeader($val) {
        // no-op
    }

    public function SetPrintFooter($val) {
        // no-op
    }

    public function SetMargins($left, $top, $right = -1, $keepmargins = false) {
        // no-op for fixed-size labels
    }

    public function SetAutoPageBreak($auto, $margin = 0) {
        // no-op
    }

    public function AddPage($orientation = '', $format = '', $keepmargins = false, $tocpage = false) {
        $this->pages[] = '';
        $this->current_page = count($this->pages) - 1;
        $this->cursor_x_pt = 0.0;
        $this->cursor_y_pt = 0.0;
    }

    public function SetFont($family, $style = '', $size = 12) {
        $this->font_size_pt = max(1.0, (float) $size);
    }

    public function SetXY($x, $y, $rtloff = false) {
        $this->cursor_x_pt = $this->mmToPt((float) $x);
        $this->cursor_y_pt = $this->mmToPt((float) $y);
    }

    public function Cell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = '', $stretch = 0, $ignore_min_height = false, $calign = 'T', $valign = 'M') {
        $width_pt = $this->mmToPt((float) $w);
        $height_pt = $this->mmToPt((float) $h);

        $x_pt = $this->cursor_x_pt;
        if ('C' === strtoupper((string) $align)) {
            $x_pt += max(0.0, ($width_pt - $this->estimateTextWidth($txt)) / 2);
        } elseif ('R' === strtoupper((string) $align)) {
            $x_pt += max(0.0, $width_pt - $this->estimateTextWidth($txt));
        }

        $y_pt = $this->cursor_y_pt + max($this->font_size_pt, $height_pt);
        $this->writeText($x_pt, $y_pt, $txt);

        if ($ln > 0) {
            $this->cursor_y_pt += $height_pt;
            $this->cursor_x_pt = 0.0;
        }
    }

    public function write1DBarcode($code, $type, $x, $y, $w, $h, $xres = 0.4, $style = [], $align = 'C') {
        $code = (string) $code;
        $x_pt = $this->mmToPt((float) $x);
        $y_pt_top = $this->mmToPt((float) $y);
        $w_pt = $this->mmToPt((float) $w);
        $h_pt = $this->mmToPt((float) $h);

        $pattern = $this->pseudoBarcodePattern($code);
        $modules = strlen($pattern);
        if ($modules <= 0) {
            return;
        }

        $module_width = $w_pt / $modules;
        $cursor = $x_pt;
        for ($i = 0; $i < $modules; $i++) {
            if ('1' === $pattern[$i]) {
                $this->drawRect($cursor, $y_pt_top, $module_width, $h_pt);
            }
            $cursor += $module_width;
        }
    }

    public function Output($name = 'doc.pdf', $dest = 'I') {
        $pdf = $this->buildPdf();
        echo $pdf;
    }

    public function Image($file, $x = null, $y = null, $w = 0, $h = 0, $type = '', $link = '', $align = '', $resize = false, $dpi = 300, $palign = '', $ismask = false, $imgmask = false, $border = 0, $fitbox = false, $hidden = false, $fitonpage = false, $alt = false, $altimgs = []) {
        if (!is_string($file) || '' === $file || !file_exists($file) || !is_readable($file)) {
            return false;
        }

        $img = @imagecreatefrompng($file);
        if (false === $img) {
            return false;
        }

        $px_w = imagesx($img);
        $px_h = imagesy($img);
        if ($px_w <= 0 || $px_h <= 0) {
            imagedestroy($img);
            return false;
        }

        $target_w_mm = ($w > 0) ? (float) $w : ($px_w * 25.4 / max(1, (float) $dpi));
        $target_h_mm = ($h > 0) ? (float) $h : ($px_h * 25.4 / max(1, (float) $dpi));

        $x_pt = $this->mmToPt((null === $x) ? ($this->cursor_x_pt / $this->unit_scale) : (float) $x);
        $y_pt_top = $this->mmToPt((null === $y) ? ($this->cursor_y_pt / $this->unit_scale) : (float) $y);
        $w_pt = $this->mmToPt($target_w_mm);
        $h_pt = $this->mmToPt($target_h_mm);

        $this->renderRasterImage($img, $x_pt, $y_pt_top, $w_pt, $h_pt);
        imagedestroy($img);
        return true;
    }

    protected function pseudoBarcodePattern($code) {
        // Deterministic visual barcode-like pattern; not a full Code128 implementation.
        $bits = '';
        $len = strlen($code);
        if ($len === 0) {
            return $bits;
        }
        for ($i = 0; $i < $len; $i++) {
            $ord = ord($code[$i]);
            $bits .= str_pad(decbin(($ord % 127) ^ ($i * 31 % 127)), 7, '0', STR_PAD_LEFT);
        }
        // add quiet zones
        return str_repeat('0', 10) . $bits . str_repeat('0', 10);
    }

    protected function writeText($x_pt, $y_pt, $text) {
        if ($this->current_page < 0) {
            $this->AddPage();
        }
        $escaped = $this->escapeText($text);
        $y_from_bottom = $this->page_height_pt - $y_pt;
        $cmd = sprintf("BT /F1 %.2F Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n", $this->font_size_pt, $x_pt, $y_from_bottom, $escaped);
        $this->pages[$this->current_page] .= $cmd;
    }

    protected function drawRect($x_pt, $y_pt_top, $w_pt, $h_pt) {
        if ($this->current_page < 0) {
            $this->AddPage();
        }
        $y_from_bottom = $this->page_height_pt - ($y_pt_top + $h_pt);
        $cmd = sprintf("%.2F %.2F %.2F %.2F re f\n", $x_pt, $y_from_bottom, $w_pt, $h_pt);
        $this->pages[$this->current_page] .= $cmd;
    }

    protected function estimateTextWidth($text) {
        $len = strlen((string) $text);
        return $len * $this->font_size_pt * 0.5;
    }

    protected function escapeText($text) {
        $text = (string) $text;
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace('(', '\\(', $text);
        $text = str_replace(')', '\\)', $text);
        return $text;
    }

    protected function mmToPt($mm) {
        return (float) $mm * $this->unit_scale;
    }

    protected function renderRasterImage($img, $x_pt, $y_pt_top, $w_pt, $h_pt) {
        $px_w = imagesx($img);
        $px_h = imagesy($img);
        if ($px_w <= 0 || $px_h <= 0 || $w_pt <= 0 || $h_pt <= 0) {
            return;
        }

        $step_x = max(1, (int) ceil($px_w / 240));
        $step_y = max(1, (int) ceil($px_h / 80));
        $cell_w = $w_pt / max(1, (int) ceil($px_w / $step_x));
        $cell_h = $h_pt / max(1, (int) ceil($px_h / $step_y));

        for ($py = 0, $row = 0; $py < $px_h; $py += $step_y, $row++) {
            for ($px = 0, $col = 0; $px < $px_w; $px += $step_x, $col++) {
                $rgb = imagecolorat($img, $px, $py);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $lum = (0.299 * $r) + (0.587 * $g) + (0.114 * $b);
                if ($lum < 128) {
                    $this->drawRect(
                        $x_pt + ($col * $cell_w),
                        $y_pt_top + ($row * $cell_h),
                        $cell_w,
                        $cell_h
                    );
                }
            }
        }
    }

    protected function buildPdf() {
        $objects = [];

        // 1: catalog (filled later)
        // 2: pages root (filled later)

        $objects[3] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";

        $page_object_ids = [];
        $content_object_ids = [];
        $next_id = 4;

        foreach ($this->pages as $page_stream) {
            $content_id = $next_id++;
            $stream = "<< /Length " . strlen($page_stream) . " >>\nstream\n" . $page_stream . "endstream";
            $objects[$content_id] = $stream;
            $content_object_ids[] = $content_id;

            $page_id = $next_id++;
            $page_dict = sprintf(
                "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 3 0 R >> >> /Contents %d 0 R >>",
                $this->page_width_pt,
                $this->page_height_pt,
                $content_id
            );
            $objects[$page_id] = $page_dict;
            $page_object_ids[] = $page_id;
        }

        $kids = '';
        foreach ($page_object_ids as $pid) {
            $kids .= $pid . " 0 R ";
        }

        $objects[2] = "<< /Type /Pages /Kids [" . trim($kids) . "] /Count " . count($page_object_ids) . " >>";
        $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";

        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0 => 0];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $body . "\nendobj\n";
        }

        $xref_pos = strlen($pdf);
        $max_id = max(array_keys($objects));
        $pdf .= "xref\n0 " . ($max_id + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $max_id; $i++) {
            $offset = isset($offsets[$i]) ? $offsets[$i] : 0;
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= "trailer\n<< /Size " . ($max_id + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref_pos . "\n%%EOF";
        return $pdf;
    }
}
