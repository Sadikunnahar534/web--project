<?php
// ============================================
// Tiny dependency-free PDF writer (text only, A4, Helvetica).
// Enough for the result report - no Composer / library needed.
// Non-English characters (e.g. Bangla) are printed as '?'.
// ============================================

class SimplePDF {
    private $pages = [];
    private $cur = '';
    private $W = 595.28;
    private $H = 841.89;
    private $ml = 45;
    private $mr = 45;
    private $mt = 50;
    private $mb = 55;
    private $y;

    // Helvetica glyph widths for ASCII 32..126 (units of 1/1000 em)
    private static $w = [
        278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,
        556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,
        1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,
        667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,
        333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,
        556,556,333,500,278,556,500,722,500,500,500,334,260,334,584
    ];

    public function __construct() {
        $this->y = $this->H - $this->mt;
    }

    public static function clean($s) {
        $s = str_replace(["\r\n", "\r", "\n", "\t"], ' ', (string)$s);
        $t = @preg_replace('/[^\x20-\x7E]/u', '?', $s);
        if ($t === null) { $t = preg_replace('/[^\x20-\x7E]/', '?', $s); }
        return $t;
    }

    private function width($s, $size, $bold) {
        $total = 0;
        $n = strlen($s);
        for ($i = 0; $i < $n; $i++) {
            $c = ord($s[$i]);
            $total += self::$w[$c - 32] ?? 556;
        }
        return $total * $size / 1000 * ($bold ? 1.07 : 1.0);
    }

    private function wrap($s, $size, $bold, $maxW) {
        $lines = [];
        $line = '';
        foreach (explode(' ', $s) as $word) {
            // hard-split words that are longer than a whole line
            while ($this->width($word, $size, $bold) > $maxW && strlen($word) > 1) {
                $cut = strlen($word) - 1;
                while ($cut > 1 && $this->width(substr($word, 0, $cut), $size, $bold) > $maxW) $cut--;
                if ($line !== '') { $lines[] = $line; $line = ''; }
                $lines[] = substr($word, 0, $cut);
                $word = substr($word, $cut);
            }
            $try = ($line === '') ? $word : $line . ' ' . $word;
            if ($this->width($try, $size, $bold) <= $maxW) {
                $line = $try;
            } else {
                if ($line !== '') $lines[] = $line;
                $line = $word;
            }
        }
        $lines[] = $line;
        return $lines;
    }

    private function esc($s) {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
    }

    private function newPage() {
        $this->pages[] = $this->cur;
        $this->cur = '';
        $this->y = $this->H - $this->mt;
    }

    public function space($pts) {
        $this->y -= $pts;
        if ($this->y < $this->mb) $this->newPage();
    }

    public function rule() {
        $this->y -= 4;
        if ($this->y < $this->mb) $this->newPage();
        $this->cur .= sprintf("q 0.80 0.83 0.90 RG 0.6 w %.2f %.2f m %.2f %.2f l S Q\n",
            $this->ml, $this->y, $this->W - $this->mr, $this->y);
        $this->y -= 6;
    }

    /** Writes wrapped text at the current position and moves down. */
    public function line($text, $size = 10, $bold = false, $rgb = [0, 0, 0], $indent = 0, $after = 0) {
        $text = self::clean($text);
        $maxW = $this->W - $this->ml - $this->mr - $indent;
        $lh = $size * 1.35;
        foreach ($this->wrap($text, $size, $bold, $maxW) as $ln) {
            if ($this->y - $lh < $this->mb) $this->newPage();
            $this->y -= $lh;
            $this->cur .= sprintf("BT /%s %.1f Tf %.3f %.3f %.3f rg 1 0 0 1 %.2f %.2f Tm (%s) Tj ET\n",
                $bold ? 'F2' : 'F1', $size, $rgb[0], $rgb[1], $rgb[2],
                $this->ml + $indent, $this->y, $this->esc($ln));
        }
        $this->y -= $after;
    }

    /** Returns the finished PDF file as a binary string. */
    public function output() {
        $pages = $this->pages;
        $pages[] = $this->cur;
        $n = count($pages);

        $objs = [];
        $kids = '';
        for ($i = 0; $i < $n; $i++) { $kids .= (5 + 2 * $i) . ' 0 R '; }
        $objs[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objs[2] = "<< /Type /Pages /Kids [" . trim($kids) . "] /Count $n >>";
        $objs[3] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
        $objs[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";

        for ($i = 0; $i < $n; $i++) {
            $content = $pages[$i];
            $content .= sprintf("BT /F1 8 Tf 0.5 0.5 0.5 rg 1 0 0 1 %.2f 28 Tm (Page %d of %d) Tj ET\n", $this->ml, $i + 1, $n);
            $pid = 5 + 2 * $i;
            $cid = 6 + 2 * $i;
            $objs[$pid] = sprintf("<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2f %.2f] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>", $this->W, $this->H, $cid);
            $objs[$cid] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream";
        }

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        ksort($objs);
        foreach ($objs as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "$id 0 obj\n$body\nendobj\n";
        }
        $count = max(array_keys($objs)) + 1;
        $xref = strlen($pdf);
        $pdf .= "xref\n0 $count\n0000000000 65535 f \n";
        for ($id = 1; $id < $count; $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
        }
        $pdf .= "trailer\n<< /Size $count /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
        return $pdf;
    }
}
