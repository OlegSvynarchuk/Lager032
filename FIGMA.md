# Figma — design source for the lager032 theme

**File:** Lager — https://www.figma.com/design/rkOC41hpF2Dx1HR93xt0Fb/Lager
**File key:** `rkOC41hpF2Dx1HR93xt0Fb`

## Access token (secret — NOT in this repo)

The Figma API token lives in a **gitignored** file `.figma-token` at the repo root, so it
never reaches GitHub (GitHub auto-revokes committed Figma tokens). On each laptop, create it
once:

```powershell
Set-Content -Path .figma-token -Value '<YOUR_FIGMA_TOKEN>' -NoNewline
```

> Get a token from Figma → Settings → Security → *Personal access tokens* (scope: File read).
> If a token is ever pasted somewhere public, rotate it there.

## Key node IDs

| Node | ID | Notes |
|---|---|---|
| Home (full page) | `47:2419` | the Početna design |
| Header (utility bar + nav) | `47:2420` | logo node: `I47:2420;3:1609` |
| Hero / slider | `47:2427` | title `47:2430`, subtitle `47:2431` |
| "Naša ponuda" heading | `47:2493` | |
| Category grid | `47:2495` | 12 cards `47:2496`–`47:2507` |
| About | `47:2433` | "Vaš partner u industrijskoj nabavci" |
| Contact | `47:2508` | "Za sva pitanja kontaktirajte nas" + form |
| Footer | `47:2494` | |
| Product Page (archive) | `47:2256` | on the "Clean" page — for later |
| Product Details Page | `47:2291` | on the "Clean" page — for later |

## Design tokens

- **Navy** `#112955` (top bar, hero overlay, headings) · **Red** `#D60000` (buttons, logo) · **Nav ink** `#1C290D`
- **Fonts:** Lato (hero title 700/48), Inter (body + section headings 700/36), Roboto Condensed (card labels 700/18)

## Fetch helpers (PowerShell)

```powershell
$token   = (Get-Content .figma-token -Raw).Trim()
$headers = @{ 'X-Figma-Token' = $token }
$key     = 'rkOC41hpF2Dx1HR93xt0Fb'

# Render a node to PNG (for visual reference)
$id  = '47:2433'                      # e.g. the About section
$img = Invoke-RestMethod "https://api.figma.com/v1/images/$key?ids=$id&format=png&scale=2" -Headers $headers
Invoke-WebRequest $img.images.$id -OutFile "screenshots/about.png"

# Inspect a node's JSON (colours, fonts, text)
$n = Invoke-RestMethod "https://api.figma.com/v1/files/$key/nodes?ids=$id" -Headers $headers
$n.nodes.$id.document | ConvertTo-Json -Depth 6
```

Exported theme assets (logo, hero photo, brand logos) already live under
`lager032/assets/img/` and are committed with the theme.
