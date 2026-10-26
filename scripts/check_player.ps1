$path = 'C:\xampp\htdocs\proyecto-websockets\emisor-receptor\resources\views\tutti\player.blade.php'
$raw = Get-Content -Path $path -Raw

function Find-String($haystack, $needle) {
    $idx = $haystack.IndexOf($needle, [System.StringComparison]::Ordinal)
    return $idx
}

$a = Find-String $raw 'function connectWebSocket'
$b = Find-String ($raw.Substring($a + 10)) 'function connectWebSocket'

Write-Output "primera en $a, segunda en $b, total $($raw.Length)"

# Mostrar desde el final de la primera función hasta el final
$from = [Math]::Min($a + 4000, $raw.Length - 100)
$rest = $raw.Substring($from)
Write-Output "---- RESTO ----"
Write-Output $rest