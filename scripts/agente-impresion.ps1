<#
.SYNOPSIS
    Agente Local de Impresión Térmica ESC/POS para CaliFit Sistema POS (SaborCaliFit Bochalema).
.DESCRIPTION
    Realiza sondeo continuo (polling) a la cola centralizada del servidor (/api/print/siguiente)
    y envía los bytes crudos ESC/POS directamente a la impresora térmica de 80 mm (DigitalPOS DIG-E200I)
    vía USB (spooler RAW de Windows) o Red Local TCP (puerto 9100).
.EXAMPLE
    .\agente-impresion.ps1 -ServerUrl "http://localhost:8081" -ApiToken "TU_TOKEN_SECRETO" -PrinterName "DigitalPOS DIG-E200I"
#>

param (
    [Parameter(Mandatory = $false)]
    [string]$ServerUrl = "http://localhost:8081",

    [Parameter(Mandatory = $false)]
    [string]$ApiToken = "califit_agent_secret_token_change_in_prod",

    [Parameter(Mandatory = $false)]
    [string]$PrinterName = "DigitalPOS DIG-E200I",

    [Parameter(Mandatory = $false)]
    [ValidateSet("USB", "Network")]
    [string]$PrinterMode = "USB",

    [Parameter(Mandatory = $false)]
    [string]$PrinterHost = "192.168.1.200",

    [Parameter(Mandatory = $false)]
    [int]$PrinterPort = 9100,

    [Parameter(Mandatory = $false)]
    [int]$PollIntervalSeconds = 1,

    [Parameter(Mandatory = $false)]
    [string]$LogFile = "$PSScriptRoot\agente.log"
)

# -------------------------------------------------------------------------
# Definición C# P/Invoke para enviar Bytes Crudos a la Impresora de Windows
# -------------------------------------------------------------------------
$RawPrinterHelperCode = @"
using System;
using System.IO;
using System.Runtime.InteropServices;

public class RawPrinterHelper
{
    [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Ansi)]
    public class DOCINFOA
    {
        [MarshalAs(UnmanagedType.LPStr)] public string pDocName;
        [MarshalAs(UnmanagedType.LPStr)] public string pOutputFile;
        [MarshalAs(UnmanagedType.LPStr)] public string pDataType;
    }

    [DllImport("winspool.Drv", EntryPoint = "OpenPrinterA", SetLastError = true, CharSet = CharSet.Ansi, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
    public static extern bool OpenPrinter([MarshalAs(UnmanagedType.LPStr)] string szPrinter, out IntPtr hPrinter, IntPtr pd);

    [DllImport("winspool.Drv", EntryPoint = "ClosePrinter", SetLastError = true, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
    public static extern bool ClosePrinter(IntPtr hPrinter);

    [DllImport("winspool.Drv", EntryPoint = "StartDocPrinterA", SetLastError = true, CharSet = CharSet.Ansi, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
    public static extern bool StartDocPrinter(IntPtr hPrinter, int level, [In, MarshalAs(UnmanagedType.LPStruct)] DOCINFOA di);

    [DllImport("winspool.Drv", EntryPoint = "EndDocPrinter", SetLastError = true, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
    public static extern bool EndDocPrinter(IntPtr hPrinter);

    [DllImport("winspool.Drv", EntryPoint = "StartPagePrinter", SetLastError = true, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
    public static extern bool StartPagePrinter(IntPtr hPrinter);

    [DllImport("winspool.Drv", EntryPoint = "EndPagePrinter", SetLastError = true, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
    public static extern bool EndPagePrinter(IntPtr hPrinter);

    [DllImport("winspool.Drv", EntryPoint = "WritePrinter", SetLastError = true, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
    public static extern bool WritePrinter(IntPtr hPrinter, IntPtr pBytes, int dwCount, out int dwWritten);

    public static bool SendBytesToPrinter(string szPrinterName, byte[] bytes)
    {
        IntPtr pUnmanagedBytes = Marshal.AllocCoTaskMem(bytes.Length);
        Marshal.Copy(bytes, 0, pUnmanagedBytes, bytes.Length);

        IntPtr hPrinter = IntPtr.Zero;
        DOCINFOA di = new DOCINFOA();
        di.pDocName = "CaliFit ESCPOS Receipt";
        di.pDataType = "RAW";

        bool success = false;
        if (OpenPrinter(szPrinterName.Normalize(), out hPrinter, IntPtr.Zero))
        {
            if (StartDocPrinter(hPrinter, 1, di))
            {
                if (StartPagePrinter(hPrinter))
                {
                    int dwWritten = 0;
                    success = WritePrinter(hPrinter, pUnmanagedBytes, bytes.Length, out dwWritten);
                    EndPagePrinter(hPrinter);
                }
                EndDocPrinter(hPrinter);
            }
            ClosePrinter(hPrinter);
        }
        Marshal.FreeCoTaskMem(pUnmanagedBytes);
        return success;
    }
}
"@

try {
    Add-Type -TypeDefinition $RawPrinterHelperCode -ErrorAction SilentlyContinue
} catch {
    # El tipo ya podría estar cargado en la sesión
}

# -------------------------------------------------------------------------
# Utilidades de Log con Rotación
# -------------------------------------------------------------------------
function Write-AgentLog {
    param (
        [string]$Message,
        [string]$Level = "INFO"
    )
    $timestamp = (Get-Date).ToString("yyyy-MM-dd HH:mm:ss")
    $logLine = "[$timestamp] [$Level] $Message"
    Write-Host $logLine -ForegroundColor $(if ($Level -eq "ERROR") { "Red" } elseif ($Level -eq "SUCCESS") { "Green" } else { "Cyan" })

    try {
        # Rotar log si supera 5 MB
        if (Test-Path $LogFile) {
            $fileSize = (Get-Item $LogFile).Length
            if ($fileSize -gt 5MB) {
                Move-Item -Path $LogFile -Destination "$LogFile.old" -Force
            }
        }
        Add-Content -Path $LogFile -Value $logLine -ErrorAction SilentlyContinue
    } catch {}
}

# -------------------------------------------------------------------------
# Función de Impresión de Bytes
# -------------------------------------------------------------------------
function Send-EscPosBytes {
    param (
        [byte[]]$Bytes
    )

    if ($PrinterMode -eq "Network") {
        try {
            $tcpClient = New-Object System.Net.Sockets.TcpClient
            $tcpClient.Connect($PrinterHost, $PrinterPort)
            $stream = $tcpClient.GetStream()
            $stream.Write($Bytes, 0, $Bytes.Length)
            $stream.Flush()
            $stream.Close()
            $tcpClient.Close()
            return $true
        } catch {
            Write-AgentLog "Fallo en envio TCP a $PrinterHost`:$PrinterPort : $($_.Exception.Message)" "ERROR"
            return $false
        }
    } else {
        try {
            $printed = [RawPrinterHelper]::SendBytesToPrinter($PrinterName, $Bytes)
            if (-not $printed) {
                Write-AgentLog "winspool.Drv rechazo la impresion en '$PrinterName'. Verifica que la impresora este encendida y el nombre sea exacto." "ERROR"
            }
            return $printed
        } catch {
            Write-AgentLog "Excepcion al enviar bytes a '$PrinterName': $($_.Exception.Message)" "ERROR"
            return $false
        }
    }
}

# -------------------------------------------------------------------------
# Bucle Principal de Polling
# -------------------------------------------------------------------------
Clear-Host
Write-Host "=====================================================================" -ForegroundColor Green
Write-Host "     CALIFIT SISTEMA POS - AGENTE CENTRALIZADO DE IMPRESION 80MM     " -ForegroundColor Green
Write-Host "                  SABORCALIFIT | BOCHALEMA                           " -ForegroundColor Green
Write-Host "=====================================================================" -ForegroundColor Green
Write-Host "Servidor:       $ServerUrl" -ForegroundColor Yellow
Write-Host "Modo:           $PrinterMode" -ForegroundColor Yellow
if ($PrinterMode -eq "Network") {
    Write-Host "Destino Red:    $PrinterHost`:$PrinterPort" -ForegroundColor Yellow
} else {
    Write-Host "Impresora USB:  $PrinterName" -ForegroundColor Yellow
}
Write-Host "Intervalo:      $PollIntervalSeconds s" -ForegroundColor Yellow
Write-Host "Archivo Log:    $LogFile" -ForegroundColor Yellow
Write-Host "---------------------------------------------------------------------"
Write-AgentLog "Iniciando servicio de agente de impresion en espera de tickets..." "INFO"

$endpointSiguiente = "$ServerUrl/api/print/siguiente"
$headers = @{
    "X-Print-Token" = $ApiToken
    "Accept"        = "application/json"
}

$consecutiveErrors = 0

while ($true) {
    try {
        $response = Invoke-RestMethod -Uri $endpointSiguiente -Method Post -Headers $headers -TimeoutSec 10 -ErrorAction Stop
        $consecutiveErrors = 0

        if ($null -ne $response -and $null -ne $response.id) {
            $jobId = $response.id
            $tipo = $response.tipo
            $base64 = $response.contenido

            Write-AgentLog "Ticket recibido #$jobId (tipo: $tipo). Decodificando..." "INFO"
            $bytes = [System.Convert]::FromBase64String($base64)

            $success = Send-EscPosBytes -Bytes $bytes

            if ($success) {
                Write-AgentLog "Impresion fisica completada para ticket #$jobId." "SUCCESS"
                Invoke-RestMethod -Uri "$ServerUrl/api/print/listo/$jobId" -Method Post -Headers $headers -TimeoutSec 10 | Out-Null
            } else {
                Write-AgentLog "Fallo impresion del ticket #$jobId. Reportando a cola..." "ERROR"
                Invoke-RestMethod -Uri "$ServerUrl/api/print/error/$jobId" -Method Post -Headers $headers -TimeoutSec 10 | Out-Null
            }
        } else {
            # Sin trabajos pendientes, esperar intervalo
            Start-Sleep -Seconds $PollIntervalSeconds
        }
    } catch {
        $statusCode = $_.Exception.Response.StatusCode.value__
        if ($statusCode -eq 401) {
            Write-AgentLog "ERROR 401: Token X-Print-Token no autorizado. Revisa la variable PRINT_AGENT_TOKEN." "ERROR"
            Start-Sleep -Seconds 10
        } else {
            $consecutiveErrors++
            if ($consecutiveErrors -le 3 -or $consecutiveErrors % 30 -eq 0) {
                Write-AgentLog "No se pudo conectar al servidor ($ServerUrl): $($_.Exception.Message)" "ERROR"
            }
            Start-Sleep -Seconds 3
        }
    }
}

