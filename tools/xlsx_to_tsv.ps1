<#
  xlsx_to_tsv.ps1 - dumps one worksheet of an .xlsx file to a tab-separated
  text file (row number in column 1, then the cells A, B, C ...), using only
  built-in Windows PowerShell. Dates stay as Excel serial numbers.

  Usage:  powershell -File tools\xlsx_to_tsv.ps1 -Xlsx <file.xlsx> -Out <out.tsv> [-Sheet 1]
#>
param(
    [Parameter(Mandatory = $true)][string]$Xlsx,
    [Parameter(Mandatory = $true)][string]$Out,
    [int]$Sheet = 1
)

$tmp = Join-Path ([IO.Path]::GetTempPath()) ("xlsx_" + [guid]::NewGuid().ToString("N"))
New-Item -ItemType Directory $tmp | Out-Null
try {
    Copy-Item $Xlsx "$tmp\book.zip"
    Expand-Archive "$tmp\book.zip" "$tmp\x"

    [xml]$ss = Get-Content "$tmp\x\xl\sharedStrings.xml" -Raw -Encoding UTF8
    $strings = @()
    foreach ($si in $ss.sst.si) {
        if ($si.t -is [string]) { $strings += $si.t }
        elseif ($si.t.'#text') { $strings += $si.t.'#text' }
        else { $strings += (($si.r | ForEach-Object { if ($_.t -is [string]) { $_.t } else { $_.t.'#text' } }) -join '') }
    }

    function Get-ColumnIndex($ref) {
        $letters = ($ref -replace '\d', '')
        $n = 0
        foreach ($ch in $letters.ToCharArray()) { $n = $n * 26 + ([int]$ch - 64) }
        return $n - 1
    }

    [xml]$ws = Get-Content "$tmp\x\xl\worksheets\sheet$Sheet.xml" -Raw -Encoding UTF8
    $lines = New-Object System.Collections.ArrayList
    foreach ($row in $ws.worksheet.sheetData.row) {
        $cells = @{}
        foreach ($c in $row.c) {
            $v = $c.v
            if ($v -is [System.Xml.XmlElement]) { $v = $v.'#text' }
            if ($c.t -eq 's' -and $null -ne $v) { $v = $strings[[int]$v] }
            elseif ($c.t -eq 'inlineStr') { $v = $c.is.t }
            if ($null -ne $v) { $cells[(Get-ColumnIndex $c.r)] = ([string]$v) -replace "[\t\r\n]+", ' ' }
        }
        if ($cells.Count -eq 0) { continue }
        $max = ($cells.Keys | Measure-Object -Maximum).Maximum
        $vals = for ($i = 0; $i -le $max; $i++) { if ($cells.ContainsKey($i)) { $cells[$i] } else { '' } }
        [void]$lines.Add("$($row.r)`t" + ($vals -join "`t"))
    }
    [IO.File]::WriteAllLines($Out, $lines, (New-Object Text.UTF8Encoding $false))
    "Wrote $($lines.Count) rows to $Out"
}
finally {
    Remove-Item $tmp -Recurse -Force -ErrorAction SilentlyContinue
}
