# ================================================================
# Setup-ClaudeGLM.ps1
#
# Sets up multi-profile Claude Code:
#   claude       -> Claude.ai login (Opus) - all phases except implement
#   g-claude     -> GLM via Z.ai API key   - speckit implement only
#   q-claude     -> Qwen 3.5 Plus via DashScope
#   init-claude  -> NEW project: creates CLAUDE.md + read/ directory
#   adopt-claude -> EXISTING project: adds read/ without touching CLAUDE.md
#
# Usage:
#   .\Setup-ClaudeGLM.ps1
# ================================================================

function Write-Header  { param($msg) Write-Host "`n-- $msg" -ForegroundColor Cyan }
function Write-Success { param($msg) Write-Host "  [OK] $msg" -ForegroundColor Green }
function Write-Info    { param($msg) Write-Host "  [..] $msg" -ForegroundColor Yellow }
function Write-Err     { param($msg) Write-Host "  [!!] $msg" -ForegroundColor Red }

Write-Host @'
  Claude Code Multi-Profile Setup
  claude -> Opus  |  g-claude -> GLM  |  q-claude -> Qwen  |  init-claude / adopt-claude
'@ -ForegroundColor Cyan


# -- Step 1: API Keys (hardcoded) ──────────────────────────────
Write-Header "Step 1: API Keys"
Write-Info "claude uses your Claude.ai browser login - no API key needed for Opus."

$ZaiApiKey = "986cf7cd0f2840fb97a6223ded350268.pGbtSMBrUz3jzgby"
Write-Success "Z.ai API key loaded (hardcoded)."

$QwenApiKey = "sk-sp-5e918826caa24811b6fda891df88ad30"
Write-Success "Qwen/DashScope API key loaded (hardcoded)."


# -- Step 2: Resolve Claude CLI binary path ──────────────────
Write-Header "Step 2: Locating Claude CLI"

$claudeBin = (Get-Command claude -CommandType Application -ErrorAction SilentlyContinue |
              Select-Object -First 1).Source

if ($claudeBin) {
    Write-Success "Found: $claudeBin"
} else {
    Write-Err "Claude CLI not found. Install it first:"
    Write-Info "npm install -g @anthropic-ai/claude-code"
    Write-Info "Then re-run this script."
    exit 1
}


# -- Step 3: Create Profile Directories ──────────────────────
Write-Header "Step 3: Creating Profile Directories"

$primaryDir = "$HOME\.claude"
$glmDir     = "$HOME\.claude-glm"
$qwenDir    = "$HOME\.claude-qwen"

New-Item -ItemType Directory -Force -Path $primaryDir | Out-Null
New-Item -ItemType Directory -Force -Path $glmDir     | Out-Null
New-Item -ItemType Directory -Force -Path $qwenDir    | Out-Null

Write-Success "Opus profile : $primaryDir"
Write-Success "GLM profile  : $glmDir"
Write-Success "Qwen profile : $qwenDir"


# -- Step 4: Primary Profile - Claude.ai Login (Opus) ────────
Write-Header "Step 4: Opus Profile (Claude.ai login)"

$opusJson = @'
{
  "permissions": {
    "allow": [
      "Bash",
      "Read",
      "Edit",
      "Write",
      "Fetch",
      "Web_search",
      "WebSearch",
      "WebFetch",
      "Skill(playwright-cli)",
      "Skill(get-api-docs)",
      "Skill(prisma)",
      "Skill(working-with-claude-code)",
      "Skill(developing-claude-code-plugins)",
      "mcp__plugin_playwright_playwright__browser_navigate",
      "mcp__plugin_playwright_playwright__browser_click",
      "mcp__plugin_context7_context7__resolve-library-id",
      "mcp__plugin_context7_context7__query-docs",
      "mcp__plugin_playwright_playwright__browser_console_messages",
      "mcp__plugin_playwright_playwright__browser_network_requests",
      "mcp__plugin_playwright_playwright__browser_wait_for",
      "mcp__plugin_playwright_playwright__browser_fill_form",
      "mcp__plugin_playwright_playwright__browser_snapshot",
      "mcp__plugin_playwright_playwright__browser_close"
    ]
  },
  "env": {}
}
'@
$opusJson | Out-File -FilePath "$primaryDir\settings.json" -Encoding UTF8

Write-Success "Written: $primaryDir\settings.json (Bash, Read, Edit, Write, fetch, web_search, WebFetch)"
Write-Info "Uses Claude.ai browser auth - no API key stored."


# -- Step 5: GLM Profile - Z.ai ──────────────────────────────
Write-Header "Step 5: GLM Profile - Z.ai"

# Update ANTHROPIC_DEFAULT_xxx_MODEL values when Z.ai publishes glm-5 model string
$glmJson = @"
{
  "permissions": {
    "allow": [
      "Bash",
      "Read",
      "Edit",
      "Write",
      "Fetch",
      "Web_search",
      "WebSearch",
      "WebFetch",
      "Skill(playwright-cli)",
      "Skill(get-api-docs)",
      "Skill(prisma)",
      "Skill(working-with-claude-code)",
      "Skill(developing-claude-code-plugins)",
      "mcp__plugin_playwright_playwright__browser_navigate",
      "mcp__plugin_playwright_playwright__browser_click",
      "mcp__plugin_context7_context7__resolve-library-id",
      "mcp__plugin_context7_context7__query-docs",
      "mcp__plugin_playwright_playwright__browser_console_messages",
      "mcp__plugin_playwright_playwright__browser_network_requests",
      "mcp__plugin_playwright_playwright__browser_wait_for",
      "mcp__plugin_playwright_playwright__browser_fill_form",
      "mcp__plugin_playwright_playwright__browser_snapshot",
      "mcp__plugin_playwright_playwright__browser_close"
    ]
  },
  "env": {
    "ANTHROPIC_AUTH_TOKEN": "$ZaiApiKey",
    "ANTHROPIC_BASE_URL": "https://api.z.ai/api/anthropic",
    "ANTHROPIC_DEFAULT_HAIKU_MODEL": "glm-5",
    "ANTHROPIC_DEFAULT_SONNET_MODEL": "glm-5",
    "ANTHROPIC_DEFAULT_OPUS_MODEL": "glm-5"
  }
}
"@
$glmJson | Out-File -FilePath "$glmDir\settings.json" -Encoding UTF8

Write-Success "Written: $glmDir\settings.json (Bash, Read, Edit, Write, fetch, web_search, WebFetch)"
Write-Info "Update model strings to glm-5 in the file above once Z.ai confirms the name."


# -- Step 5a: Qwen Profile - DashScope ────────────────────────
Write-Header "Step 5a: Qwen Profile - DashScope"

$qwenJson = @"
{
  "permissions": {
    "allow": [
      "Bash",
      "Read",
      "Edit",
      "Write",
      "Fetch",
      "Web_search",
      "WebSearch",
      "WebFetch",
      "Skill(playwright-cli)",
      "Skill(get-api-docs)",
      "Skill(prisma)",
      "Skill(working-with-claude-code)",
      "Skill(developing-claude-code-plugins)",
      "mcp__plugin_playwright_playwright__browser_navigate",
      "mcp__plugin_playwright_playwright__browser_click",
      "mcp__plugin_context7_context7__resolve-library-id",
      "mcp__plugin_context7_context7__query-docs",
      "mcp__plugin_playwright_playwright__browser_console_messages",
      "mcp__plugin_playwright_playwright__browser_network_requests",
      "mcp__plugin_playwright_playwright__browser_wait_for",
      "mcp__plugin_playwright_playwright__browser_fill_form",
      "mcp__plugin_playwright_playwright__browser_snapshot",
      "mcp__plugin_playwright_playwright__browser_close"
    ]
  },
  "env": {
    "ANTHROPIC_AUTH_TOKEN": "$QwenApiKey",
    "ANTHROPIC_BASE_URL": "https://coding-intl.dashscope.aliyuncs.com/apps/anthropic",
    "ANTHROPIC_MODEL": "qwen3.5-plus"
  }
}
"@
$qwenJson | Out-File -FilePath "$qwenDir\settings.json" -Encoding UTF8

Write-Success "Written: $qwenDir\settings.json (Qwen 3.5 Plus via DashScope)"


# -- Step 5b: Set hasCompletedOnboarding ───────────────────────
Write-Header "Step 5b: Onboarding flag"

$claudeJsonPath = "$HOME\.claude.json"
if (Test-Path $claudeJsonPath) {
    try {
        $claudeJson = Get-Content $claudeJsonPath -Raw -Encoding UTF8 | ConvertFrom-Json
        $claudeJson.hasCompletedOnboarding = $true
        $claudeJson | ConvertTo-Json -Depth 5 | Set-Content $claudeJsonPath -Encoding UTF8 -NoNewline
        Write-Success "Updated hasCompletedOnboarding = true in $claudeJsonPath"
    } catch {
        Write-Err "Could not update $claudeJsonPath : $_"
    }
} else {
    '{ "hasCompletedOnboarding": true }' | Out-File -FilePath $claudeJsonPath -Encoding UTF8
    Write-Success "Created $claudeJsonPath with hasCompletedOnboarding = true"
}


# -- Step 5c: Project-level .claude/settings.json ────────────
Write-Header "Step 5b: Project-level .claude/settings.json template"
Write-Info "This will be copied into projects by init-claude / adopt-claude."

$projectClaudeJson = @'
{
  "permissions": {
    "allow": [
      "Bash",
      "Read",
      "Edit",
      "Write",
      "Fetch",
      "Web_search",
      "WebSearch",
      "WebFetch",
      "Skill(playwright-cli)",
      "Skill(get-api-docs)",
      "Skill(prisma)",
      "Skill(working-with-claude-code)",
      "Skill(developing-claude-code-plugins)",
      "mcp__plugin_playwright_playwright__browser_navigate",
      "mcp__plugin_playwright_playwright__browser_click",
      "mcp__plugin_context7_context7__resolve-library-id",
      "mcp__plugin_context7_context7__query-docs",
      "mcp__plugin_playwright_playwright__browser_console_messages",
      "mcp__plugin_playwright_playwright__browser_network_requests",
      "mcp__plugin_playwright_playwright__browser_wait_for",
      "mcp__plugin_playwright_playwright__browser_fill_form",
      "mcp__plugin_playwright_playwright__browser_snapshot",
      "mcp__plugin_playwright_playwright__browser_close"
    ]
  }
}
'@

New-Item -ItemType Directory -Force -Path "$HOME\.claude-templates" | Out-Null
$projectClaudeJson | Out-File -FilePath "$HOME\.claude-templates\.claude-settings.json" -Encoding UTF8

Write-Success "Saved project .claude/settings.json template to ~/.claude-templates/"


# -- Step 5c: Add WebFetch + MCP permissions to this project's .claude/settings.json if missing ─
Write-Header "Step 5c: Current project .claude/settings.json (add WebFetch + MCP if missing)"
$runDir = if ($PSScriptRoot) { $PSScriptRoot } else { Split-Path -Parent $MyInvocation.MyCommand.Path }
$projectSettingsPath = Join-Path $runDir ".claude\settings.json"
$permissionsToEnsure = @(
    "WebSearch",
    "Skill(playwright-cli)",
    "Skill(get-api-docs)",
    "Skill(prisma)",
    "Skill(working-with-claude-code)",
    "Skill(developing-claude-code-plugins)",
    "WebFetch",
    "mcp__plugin_playwright_playwright__browser_navigate",
    "mcp__plugin_playwright_playwright__browser_click",
    "mcp__plugin_context7_context7__resolve-library-id",
    "mcp__plugin_context7_context7__query-docs",
    "mcp__plugin_playwright_playwright__browser_console_messages",
    "mcp__plugin_playwright_playwright__browser_network_requests",
    "mcp__plugin_playwright_playwright__browser_wait_for",
    "mcp__plugin_playwright_playwright__browser_fill_form",
    "mcp__plugin_playwright_playwright__browser_snapshot",
    "mcp__plugin_playwright_playwright__browser_close"
)
if (Test-Path $projectSettingsPath) {
    try {
        $projectSettings = Get-Content $projectSettingsPath -Raw -Encoding UTF8 | ConvertFrom-Json
        $allow = [System.Collections.ArrayList]@($projectSettings.permissions.allow)
        $added = @()
        foreach ($perm in $permissionsToEnsure) {
            if ($allow -notcontains $perm) {
                $allow.Add($perm) | Out-Null
                $added += $perm
            }
        }
        if ($added.Count -gt 0) {
            $projectSettings.permissions.allow = @($allow)
            $projectSettings | ConvertTo-Json -Depth 5 | Set-Content $projectSettingsPath -Encoding UTF8 -NoNewline
            Write-Success "Added to $projectSettingsPath : $($added -join ', ')"
        } else {
            Write-Info "WebFetch + MCP permissions already present - skipped."
        }
    } catch {
        Write-Err "Could not update project settings: $_"
    }
} else {
    Write-Info "No .claude/settings.json in current project - skip."
}


# -- Step 6: Save global CLAUDE.md template to user home ─────
Write-Header "Step 6: Saving global AI memory templates to ~\.claude-templates"

$templatesDir = "$HOME\.claude-templates"
$rulesDir     = "$templatesDir\rules"
New-Item -ItemType Directory -Force -Path $rulesDir | Out-Null

# Load templates from embedded files (same dir as script)
$scriptDir = if ($PSScriptRoot) { $PSScriptRoot } else { Split-Path -Parent $MyInvocation.MyCommand.Path }
$templatesSrc = Join-Path $scriptDir "Setup-ClaudeGLM-templates"
if (Test-Path $templatesSrc) {
    Copy-Item (Join-Path $templatesSrc "*") -Destination $templatesDir -Recurse -Force
    Copy-Item (Join-Path $templatesSrc "rules\*") -Destination $rulesDir -Force -ErrorAction SilentlyContinue
    Write-Success "Templates copied from $templatesSrc"
} else {
    # Fallback: create minimal templates inline
    $claudeMd = "# CLAUDE.md - Fill per project. Run init-claude for full template."
    $claudeMd | Out-File "$templatesDir\CLAUDE.md" -Encoding UTF8
    "read/project-context.md" | Out-File "$templatesDir\project-context.md" -Encoding UTF8
    
    $minimalRule = @"
# Placeholder rule file
# Replace with actual content
"@
    
    $minimalRule | Out-File "$rulesDir\workflow.md" -Encoding UTF8
    $minimalRule | Out-File "$rulesDir\speckit.md" -Encoding UTF8
    $minimalRule | Out-File "$rulesDir\code-quality.md" -Encoding UTF8
    $minimalRule | Out-File "$rulesDir\verification.md" -Encoding UTF8
    $minimalRule | Out-File "$rulesDir\lessons.md" -Encoding UTF8
    Write-Info "Minimal templates created. For full templates, add Setup-ClaudeGLM-templates folder."
}
Write-Success "Templates ready at: $templatesDir"


# -- Step 7: Inject Functions into PowerShell Profile ────────
Write-Header "Step 7: PowerShell Profile Functions"

# Build the profile functions block with proper path substitution
$functionsBlock = @"

# --- Claude Multi Profile (do not remove this line) ---
function claude {
    `$opusSettings = "`$HOME\.claude\settings.json"
    & '$claudeBin' --setting-sources user,project --settings `$opusSettings @args
}
function g-claude {
    `$glmSettings = "`$HOME\.claude-glm\settings.json"
    & '$claudeBin' --setting-sources user,project --settings `$glmSettings @args
}
function q-claude {
    `$qwenSettings = "`$HOME\.claude-qwen\settings.json"
    & '$claudeBin' --setting-sources user,project --settings `$qwenSettings @args
}
function init-claude {
    `$tpl = "`$HOME\.claude-templates"
    if (-not (Test-Path "CLAUDE.md")) {
        Copy-Item "`$tpl\CLAUDE.md" -Destination "CLAUDE.md" -Force -ErrorAction SilentlyContinue
        if (Test-Path "CLAUDE.md") { Write-Host "Created CLAUDE.md" -ForegroundColor Green }
    }
    if (-not (Test-Path ".claude")) { New-Item -ItemType Directory -Path ".claude" -Force | Out-Null }
    if (Test-Path "`$tpl\.claude-settings.json") {
        Copy-Item "`$tpl\.claude-settings.json" -Destination ".claude\settings.json" -Force
        Write-Host "Created .claude/settings.json" -ForegroundColor Green
    }
    if (-not (Test-Path "read")) { New-Item -ItemType Directory -Path "read" -Force | Out-Null }
    Get-ChildItem "`$tpl\rules" -ErrorAction SilentlyContinue | ForEach-Object {
        Copy-Item `$_.FullName -Destination "read\" -Force
    }
    if (Test-Path "read") { Write-Host "Created read/ directory" -ForegroundColor Green }
}
function adopt-claude {
    `$tpl = "`$HOME\.claude-templates"
    if (-not (Test-Path ".claude")) { New-Item -ItemType Directory -Path ".claude" -Force | Out-Null }
    if (Test-Path "`$tpl\.claude-settings.json") {
        Copy-Item "`$tpl\.claude-settings.json" -Destination ".claude\settings.json" -Force
        Write-Host "Created .claude/settings.json" -ForegroundColor Green
    }
    if (-not (Test-Path "read")) { New-Item -ItemType Directory -Path "read" -Force | Out-Null }
    Get-ChildItem "`$tpl\rules" -ErrorAction SilentlyContinue | ForEach-Object {
        Copy-Item `$_.FullName -Destination "read\" -Force
    }
    if (Test-Path "read") { Write-Host "Added read/ directory" -ForegroundColor Green }
}
"@

# Resolve profile path
$profilePath = if ($PROFILE) { $PROFILE } else {
    "$HOME\Documents\PowerShell\Microsoft.PowerShell_profile.ps1"
}

$profileParent = Split-Path $profilePath -Parent
if (-not (Test-Path $profileParent)) {
    New-Item -ItemType Directory -Force -Path $profileParent | Out-Null
}
if (-not (Test-Path $profilePath)) {
    New-Item -ItemType File -Force -Path $profilePath | Out-Null
    Write-Info "Created new profile file: $profilePath"
}

$existing = Get-Content $profilePath -Raw -ErrorAction SilentlyContinue
$marker = 'Claude Multi Profile'
if ($existing -and $existing.Contains($marker)) {
    Write-Info "Functions already present in profile - skipping injection."
} else {
    Add-Content -Path $profilePath -Value $functionsBlock -Encoding UTF8
    Write-Success "Functions injected into: $profilePath"
}


# -- Done
Write-Host "`n" -NoNewline
Write-Host "  Setup Complete!" -ForegroundColor Green
Write-Host "  Reload your profile: . `$PROFILE" -ForegroundColor Yellow
Write-Host "  For NEW projects: init-claude | For EXISTING: adopt-claude" -ForegroundColor Yellow
Write-Host "  claude = Opus | g-claude = GLM | q-claude = Qwen" -ForegroundColor Cyan
Write-Host ""
