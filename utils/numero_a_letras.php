<?php
function numeroALetras($numero)
{
    $formatter = new NumberFormatter("es", NumberFormatter::SPELLOUT);
    $entero = floor($numero);
    $decimales = round(($numero - $entero) * 100);

    $texto = ucfirst($formatter->format($entero));
    if ($decimales > 0) {
        $texto .= " con $decimales/100";
    }
    return $texto;
}
?>
