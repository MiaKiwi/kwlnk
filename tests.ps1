$WebRoot = "http://localhost:8000/kwlnk/"
$ApiUrl = "${WebRoot}api/"
$Variables = @{}



$Script:Successes = 0
$Script:Failures = 0



function Write-Test {
    param(
        [ValidateSet("Pass", "Fail", "Intro")]
        [string] $Status,

        [string] $Message = ""
    )



    Write-Host ("`b" * 9999) -NoNewline

    Write-Host "$(([datetime]::Now).ToString("HH:mm:ss.fff")) [ " -ForegroundColor DarkGray -NoNewline

    switch ($Status) {
        "Pass" { Write-Host "PASS" -ForegroundColor Green -NoNewline }
        "Fail" { Write-Host "FAIL" -ForegroundColor Red -NoNewline }
        "Intro" { Write-Host "TEST" -ForegroundColor Yellow -NoNewline }
    }

    Write-Host " ] " -ForegroundColor DarkGray -NoNewline

    Write-Host $Message -NoNewline:($Status -eq "Intro")
}


function Set-Var {
    param(
        [Parameter(Mandatory = $true, Position = 0)]
        [string] $Name,

        [Parameter(Mandatory = $true, Position = 1)]
        [object] $Value
    )



    Write-Host "$(([datetime]::Now).ToString("HH:mm:ss.fff")) [ " -ForegroundColor DarkGray -NoNewline

    Write-Host "SVAR" -ForegroundColor Cyan -NoNewline

    Write-Host " ] " -ForegroundColor DarkGray -NoNewline

    Write-Host "Setting variable '$Name' to '$Value'"



    $Variables[$Name] = $Value
}



function Get-Var {
    param(
        [Parameter(Mandatory = $true, Position = 0)]
        [string] $Name,

        [Parameter(Mandatory = $false, Position = 1)]
        [Alias('Default')]
        $DefaultValue = $null,

        [Alias('Pool')]
        [hashtable] $VariablesPool = $Variables
    )



    if ($VariablesPool.ContainsKey($Name)) {
        $Value = $VariablesPool[$Name]
    }
    else {
        $Value = $DefaultValue
    }



    Write-Host "$(([datetime]::Now).ToString("HH:mm:ss.fff")) [ " -ForegroundColor DarkGray -NoNewline

    Write-Host "GVAR" -ForegroundColor DarkCyan -NoNewline

    Write-Host " ] " -ForegroundColor DarkGray -NoNewline

    Write-Host "Getting variable '$Name' with value '$Value'"



    return $Value
}



function Test-KapirApi {
    param(
        [Parameter(Mandatory = $true, Position = 0)]
        [string] $Endpoint,
        
        [string] $Message,

        [ValidateSet("GET", "POST", "PUT", "DELETE", "PATCH")]
        [string] $Method = "GET",

        [hashtable] $Headers = @{},

        [hashtable] $Body = @{},

        [Alias('Expect')]
        [hashtable] $Expected = @{
            status = 'success';
            error  = $null;
        },

        [string] $Url = $ApiUrl,

        [switch] $Fatal,

        [switch] $Void
    )


    
    $Uri = $Url + $Endpoint



    if (-not $Message) {
        $Message = "$Method $Uri"
    }
    
    
    
    Write-Test Intro $Message



    try {

        $Response = Invoke-RestMethod -Uri $Uri -Method $Method -Headers $Headers -Body $Body -ErrorAction Stop

    }
    catch {

        $StreamReader = [System.IO.StreamReader]::new($_.Exception.Response.GetResponseStream())
        $Response = $StreamReader.ReadToEnd()
        $StreamReader.Close()

    }



    try {

        $Response = $Response | ConvertFrom-Json

    }
    catch {

        # $Response | Out-String

    }



    $NotWhatYouExpected = $false

    foreach ($Key in $Expected.Keys) {

        if ($Response.$Key -ne $Expected.$Key) {
            $Script:Failures++

            Write-Test Fail "${Message}: '$Key' should be '$($Expected.$Key)' but was '$($Response.$Key)'"

            Write-Host ($Response | ConvertTo-Json -Depth 10) -ForegroundColor DarkGray

            $NotWhatYouExpected = $true

            if ($Fatal) {
                throw "${Message}: '$Key' should be '$($Expected.$Key)' but was '$($Response.$Key)'"
            }
            else {
                break
            }
        }

    }



    if (-not $NotWhatYouExpected) {
        $Script:Successes++

        Write-Test Pass $Message
    }



    if (-not $Void) {
        return $Response
    }
}



Write-Host "`nAccounts`n========"

Test-KapirApi -Message "Get current account (not logged in)" -Endpoint "users/me" -Method GET -Expected @{ status = 'error'; message = 'Authentication error.' } -Void

Test-KapirApi -Message "Login with invalid credentials" -Endpoint "login" -Method POST -Body @{ id = 'default_administrator'; password = 'invalid' } -Expected @{ status = 'error'; message = 'Invalid account ID or password.' } -Void

Test-KapirApi -Message "Try a protected action with invalid token" -Endpoint "users" -Method GET -Headers @{ Authorization = "Bearer invalid_token" } -Expected @{ status = 'error'; message = 'Authentication error.' } -Void

Set-Var 'AdminToken' (Test-KapirApi -Message "Login with valid credentials" -Endpoint "login" -Method POST -Body @{ id = 'default_administrator'; password = 'kiwis are birds but also fruits and people for some reason?' } -Expected @{ status = 'success'; error = $null } -Fatal).Data.Token.Id

Test-KapirApi -Message "Get current account (logged in)" -Endpoint "users/me" -Method GET -Headers @{ Authorization = "Bearer $(Get-Var 'AdminToken')" } -Expected @{ status = 'success' } -Void

Set-Var 'CurrentAdminTokenId' (Test-KapirApi -Message "Get all tokens" -Endpoint "users/me/tokens/current" -Method GET -Headers @{ Authorization = "Bearer $(Get-Var 'AdminToken')" } -Expected @{ status = 'success' } -Fatal).Data.Id

Test-KapirApi -Message "Get the current token" -Endpoint "users/me/tokens/$(Get-Var 'CurrentAdminTokenId')" -Method GET -Headers @{ Authorization = "Bearer $(Get-Var 'AdminToken')" } -Expected @{ status = 'success' } -Void

Set-Var 'AccountA' (Test-KapirApi -Message "Create AccountA" -Endpoint "users" -Method POST -Headers @{ Authorization = "Bearer $(Get-Var 'AdminToken')" } -Body @{ id = 'AccountA'; password = 'password'; } -Expected @{ status = 'success'; error = $null } -Fatal).Data

Test-KapirApi -Message "Change AccountA password" -Endpoint "users/$((Get-Var 'AccountA').Id)" -Method PUT -Headers @{ Authorization = "Bearer $(Get-Var 'AdminToken')" } -Body @{ password = 'Hello, World!'; } -Expected @{ status = 'success'; error = $null } -Fatal -Void

Set-Var 'AccountAToken' (Test-KapirApi -Message "Login with AccountA" -Endpoint "login" -Method POST -Body @{ id = 'AccountA'; password = 'Hello, World!'; } -Expected @{ status = 'success'; error = $null } -Fatal).Data.Token.Id

Test-KapirApi -Message "Delete AccountA" -Endpoint "users/$((Get-Var 'AccountA').Id)" -Method DELETE -Headers @{ Authorization = "Bearer $(Get-Var 'AccountAToken')" } -Expected @{ status = 'success'; error = $null } -Void

Test-KapirApi -Message "Deauthenticate" -Endpoint "logout" -Method GET -Headers @{ Authorization = "Bearer $(Get-Var 'AdminToken')" } -Expected @{ status = 'success'; error = $null } -Void



Write-Host "`nLinks`n====="

Test-KapirApi -Message "Get all links (not logged in)" -Endpoint "links" -Method GET -Expected @{ status = 'error'; message = 'Authentication error.' } -Void

Set-Var 'AdminToken' (Test-KapirApi -Message "Log in as admin" -Endpoint "login" -Method POST -Body @{ id = 'default_administrator'; password = 'kiwis are birds but also fruits and people for some reason?' } -Expected @{ status = 'success'; error = $null } -Fatal).Data.Token.Id

Test-KapirApi -Message "Get all links (logged in)" -Endpoint "links" -Method GET -Headers @{ Authorization = "Bearer $(Get-Var 'AdminToken')" } -Expected @{ status = 'success'; error = $null } -Void

Set-Var 'PresetKey' 'kwlnk-test'

Set-Var 'PresetKeyLink' (Test-KapirApi -Message "Create a link with a preset key (logged in)" -Endpoint "links" -Method POST -Headers @{ Authorization = "Bearer $(Get-Var 'AdminToken')" } -Body @{ key = (Get-Var 'PresetKey'); uri = 'https://example.com'; } -Expected @{ status = 'success'; error = $null } -Fatal).Data

Test-KapirApi -Message "Get the created link" -Endpoint "links/$((Get-Var 'PresetKeyLink').Key)" -Method GET -Headers @{ Authorization = "Bearer $(Get-Var 'AdminToken')" } -Expected @{ status = 'success'; error = $null } -Fatal -Void

Test-KapirApi -Message "Redirect to the created link" -Endpoint "$(Get-Var 'PresetKey')" -Url $WebRoot -Method GET -Expected @{ status = $null } -Void

Test-KapirApi -Message "Create a link with the same key (logged in)" -Endpoint "links" -Method POST -Headers @{ Authorization = "Bearer $(Get-Var 'AdminToken')" } -Body @{ key = (Get-Var 'PresetKey'); uri = 'https://boogaloo.example.com'; } -Expected @{ status = 'error'; message = 'Link already exists.' } -Void

Test-KapirApi -Message "Expire the link" -Endpoint "links/$((Get-Var 'PresetKeyLink').Key)" -Method PATCH -Headers @{ Authorization = "Bearer $(Get-Var 'AdminToken')" } -Body @{ expires_at = ([datetime]::now).AddDays(-1).ToString("yyyy-MM-dd HH:mm:ss") } -Expected @{ status = 'success'; error = $null } -Void

Test-KapirApi -Message "Redirect to the expired link" -Endpoint "$(Get-Var 'PresetKey')" -Url $WebRoot -Method GET -Expected @{ status = 'error'; message = 'Link has expired.' } -Void

Test-KapirApi -Message "Delete the expired link" -Endpoint "links/$((Get-Var 'PresetKeyLink').Key)" -Method DELETE -Headers @{ Authorization = "Bearer $(Get-Var 'AdminToken')" } -Expected @{ status = 'success'; error = $null } -Void

Set-Var 'RandomKeyLink' (Test-KapirApi -Message "" -Endpoint "links" -Method POST -Headers @{ Authorization = "Bearer $(Get-Var 'AdminToken')" } -Body @{ uri = 'https://mia.kiwi'; } -Expected @{ status = 'success'; error = $null } -Fatal).Data

Test-KapirApi -Message "Create a link with a random key (logged in)" -Endpoint "$((Get-Var 'RandomKeyLink').Key)" -Url $WebRoot -Method GET -Expected @{ status = $null } -Void



$Variables | Format-Table

Write-Host "`nResults`n========"
Write-Host "  ${Script:Successes} tests passed." -ForegroundColor Green
Write-Host "  ${Script:Failures} tests failed." -ForegroundColor Red