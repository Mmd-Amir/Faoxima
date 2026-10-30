
Seller API
Search…
cURL
JS
Python
English
فارسی
Getting started
GET
/ping
GET
/me
Balances
GET
/wallet
POST
/wallet/topup
POST
/wallet/cashout
GET
/wallet/convert/quote
POST
/wallet/convert
Shop
GET
/profile
PATCH
/profile
Servers
GET
/servers
PUT
/servers
Metered configs
GET
/configs
POST
/configs
GET
/configs/finished
GET
/configs/:id
GET
/configs/:id/links
POST
/configs/:id/extend
GET
/configs/:id/reset-traffic/quote
POST
/configs/:id/reset-traffic
PUT
/configs/:id/expiry
PUT
/configs/:id/tg-proxy
POST
/configs/:id/revive
PUT
/configs/:id/label
PUT
/configs/:id/note
GET
/configs/:id/servers
PUT
/configs/:id/servers
POST
/configs/:id/pause
POST
/configs/:id/resume
POST
/configs/:id/rotate-link
DELETE
/configs/:id
Telegram proxy
GET
/proxy/wallet
POST
/proxy/topup
GET
/proxies
POST
/proxies
POST
/proxies/:id/extend
DELETE
/proxies/:id
Unlimited plans
GET
/unlimited/status
GET
/unlimited/units/quote
POST
/unlimited/units/topup
GET
/unlimited
POST
/unlimited
PUT
/unlimited/:id/label
PUT
/unlimited/:id/note
POST
/unlimited/:id/pause
POST
/unlimited/:id/resume
POST
/unlimited/:id/rotate-link
DELETE
/unlimited/:id
GET
/unlimited/:id
Panel resale and sub-sellers
GET
/resale
PUT
/resale
POST
/resale/domains
POST
/resale/domains/verify
PUT
/resale/sub-domain
POST
/resale/sub-domain/verify
DELETE
/resale/sub-domain
DELETE
/resale/domains
GET
/resale/subs
POST
/resale/subs
GET
/resale/subs/:userId
PATCH
/resale/subs/:userId
POST
/resale/subs/:userId/password
POST
/resale/subs/:userId/transfer
GET
/resale/transfers
GET
/resale/commissions
GET
/resale/requests
POST
/resale/requests/:id
GET
/panel-credit
POST
/panel-credit
DELETE
/panel-credit/:id
Stats
GET
/stats
Seller API
Run the core seller workflows from your own code: create and manage metered, OpenVPN, WireGuard, unlimited and proxy products; inspect and convert balances; manage customers and servers; and pull sales stats. Build your own bot, site or app.

All responses are JSON. Traffic is returned in both bytes and GB. Timestamps are ISO 8601 in UTC.

Authentication
Create an API key in the seller panel, Developers tab. The key is shown only once, so save it right away. Every request must carry the Authorization header.

Base URL
https://su.randomatic.ir/api/v1
Auth header
Authorization: Bearer sk_live_...
Alternative
X-API-Key: sk_live_...
Rate limit
120 req / min
Subscription link
https://su.randomatic.ir/api/sub/<id>
Core concepts
Balances
There are three seller balances: GB credit for metered, OpenVPN, WireGuard and Telegram proxy products; unlimited stock; and bot/website revenue. GET /wallet returns all three beside your VPoints balance. Use /wallet/convert/quote before /wallet/convert for an allowed transfer.

Pay-as-used credit
When your account bills live (GET /wallet billing.mode is "live"), creating a metered config or proxy takes nothing from your GB credit; credit goes down only as your customers actually use traffic. Creating needs at least billing.minCreateBytes of credit. The balance may go negative down to billing.overdraftBytes; past that, your metered configs and proxies are paused (pausedBy: "credit") and resume by themselves after a top-up. In this mode extend, shrink and delete move no credit, and refundedBytes is always 0. Cashing GB credit out to VPoints (/wallet/cashout and the config-to-vpoints conversion) is closed in this mode. In "prepaid" mode everything works as before.

Unlimited plans
Unlimited is separate from GB credit. It spends unlimited stock first and falls back to VPoints. Read term pricing and costs from /unlimited/status. You can create, rename, annotate, pause, resume, rotate or delete a plan; deletion returns the unused term to unlimited stock.

Server naming
PUT /servers renames a server or hides it from your customers. The name applies to every product you sell, both metered configs and unlimited plans. To override the name or visibility for one single customer, use PUT /configs/:id/servers.

Subscription links
Every config has a subUrl, the link you give your customer. The config list is rebuilt live on each fetch, so renaming a server or adding/removing one takes effect the next time their client refreshes. To render the configs yourself, use GET /configs/:id/links.

Gaming DNS
Send proto: dns for a DNS-only product, or dns: true to deliver the DNS profile alongside an ordinary config. The customer points their device at the DNS address and game ping and YouTube start working; whatever they pull through it is metered against that same config's allowance, so it spends your GB credit exactly like every other product. Creation is refused, charging nothing, when DNS is off platform-wide or in your own Servers tab.

OpenVPN
Create it with proto: openvpn. This dedicated product delivers self-contained .ovpn files and spends the same GB credit. The subUrl opens the download page in a browser; format=json returns ovpnConfigs and each file URL. If the operator temporarily closes new OpenVPN sales, creation returns proto_unavailable and charges nothing.

Getting started
Check your key and see your limits

GET
/ping
Ping
#
The cheapest way to check that your key works.

cURL

curl -X GET "https://su.randomatic.ir/api/v1/ping" \
  -H "Authorization: Bearer $API_KEY"
Example response
JSON

{
  "ok": true,
  "api": "v1"
}
GET
/me
My account
#
Who this key belongs to, your credit, and every limit on the account.

cURL

curl -X GET "https://su.randomatic.ir/api/v1/me" \
  -H "Authorization: Bearer $API_KEY"
Example response
JSON

{
  "sellerId": "u_8f21…",
  "displayName": "aryanet",
  "isSeller": true,
  "shopName": "آریانت",
  "creditGb": 184,
  "activeConfigs": 39,
  "limits": {
    "maxConfigs": 80,
    "minMbPerConfig": 256,
    "maxMbPerConfig": 512000,
    "minTopupGb": 10,
    "rateLimitPerMinute": 120
  },
  "subBase": "https://su.randomatic.ir/api/sub",
  "docsUrl": "https://su.randomatic.ir/api/documention"
}
Balances
All balances, top-up, cash-out and conversion

GET
/wallet
All balances
#
GB credit, unlimited stock, shop revenue and VPoints, plus pricing, limits and the conversion table.

cURL

curl -X GET "https://su.randomatic.ir/api/v1/wallet" \
  -H "Authorization: Bearer $API_KEY"
Example response
JSON

{
  "creditGb": 184,
  "creditBytes": 197568495616,
  "vpPerGb": 1,
  "tomanPerGb": 4000,
  "activeConfigs": 39,
  "maxConfigs": 80,
  "tier": {
    "soldGb": 5200,
    "discountPercent": 10,
    "baseTomanPerGb": 4000,
    "effectiveTomanPerGb": 3600,
    "next": {
      "minSoldGb": 10000,
      "discountPercent": 15
    }
  },
  "minTopupGb": 10,
  "balances": {
    "vpoints": 1042,
    "sellerCreditBytes": 197568495616,
    "sellerCreditGb": 184,
    "unlimitedUnits": 12.5,
    "shopRevenueToman": 850000
  },
  "convert": {
    "prices": {
      "config": {
        "list": 4000,
        "out": 4000
      },
      "unlimited": {
        "list": 140000,
        "out": 119000
      },
      "revenue": {
        "list": 1,
        "out": 1
      }
    },
    "minimums": {
      "configGb": 1,
      "unlimitedUnits": 1,
      "revenueToman": 10000
    }
  }
}
POST
/wallet/topup
Top up credit
#
Buy GB credit, charged to your VPoints balance. The minimum is in /wallet.

Body parameters
gb
integer · required
Whole number of GB
cURL

curl -X POST "https://su.randomatic.ir/api/v1/wallet/topup" \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"gb":50}'
Example response
JSON

{
  "creditGb": 234,
  "creditBytes": 251255348224,
  "vpSpent": 50,
  "tierDiscountPercent": 10
}
POST
/wallet/cashout
Cash out credit
#
This legacy route is permanently disabled for every account and returns cashout_disabled.

Body parameters
gb
integer · required
Whole number of GB to return
cURL

curl -X POST "https://su.randomatic.ir/api/v1/wallet/cashout" \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"gb":20}'
Example response
JSON

{
  "creditGb": 214,
  "creditBytes": 229780815872,
  "vpReturned": 18,
  "vpBalance": 1042
}
GET
/wallet/convert/quote
Quote a conversion
#
Returns the exact output and applied rate before moving funds. Allowed routes include config_to_unlimited, unlimited_to_config and revenue_to_config. Revenue and VPoints are never destinations.

Query parameters
direction
string · required
Conversion direction, e.g. config_to_unlimited
amount
integer · required
Amount in the source wallet unit
cURL

curl -X GET "https://su.randomatic.ir/api/v1/wallet/convert/quote?direction=config_to_unlimited&amount=70" \
  -H "Authorization: Bearer $API_KEY"
Example response
JSON

{
  "success": true,
  "direction": "config_to_unlimited",
  "fromKey": "config",
  "toKey": "unlimited",
  "fromUnit": "gb",
  "toUnit": "unit",
  "fromAmount": 70,
  "fromCharged": 70,
  "toAmount": 2,
  "tomanPerFrom": 4000,
  "tomanPerTo": 140000
}
POST
/wallet/convert
Convert a balance
#
Executes the conversion at the current rate. The server re-quotes and guards the source balance; the response returns all three seller balances afterwards. There is no automatic undo.

Body parameters
direction
string · required
The quoted direction
amount
integer · required
Amount in the source wallet unit
cURL

curl -X POST "https://su.randomatic.ir/api/v1/wallet/convert" \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"direction":"config_to_unlimited","amount":70}'
Example response
JSON

{
  "success": true,
  "direction": "config_to_unlimited",
  "fromCharged": 70,
  "toAmount": 2,
  "sellerTrafficBytes": 122406567936,
  "sellerUnlimitedUnits": 14.5,
  "shopRevenueToman": 850000
}
Shop
Branding, shop name and subscription settings

GET
/profile
Shop profile
#
The branding shown on your subscription page and in your customers info line.

cURL

curl -X GET "https://su.randomatic.ir/api/v1/profile" \
  -H "Authorization: Bearer $API_KEY"
Example response
JSON

{
  "shopName": "آریانت",
  "brandColor": "#7c3aed",
  "accentColor": "",
  "subMode": "auto",
  "logoUrl": "",
  "resalePricePerGb": 12000,
  "infoLineEnabled": true,
  "infoLineTemplate": "",
  "defaultExpiryDays": 30
}
PATCH
/profile
Update profile
#
Only the fields you send are changed. PUT does exactly the same.

Body parameters
shopName
string
Your shop name (max 40 chars)
brandColor
hex
Primary brand color, e.g. #7c3aed
accentColor
hex
Optional secondary color
logoUrl
url
Logo URL (https)
subMode
enum
Subscription page theme: auto | light | dark
resalePricePerGb
integer
Your own resale price per GB in Toman (used for profit math only)
infoLineEnabled
boolean
Show the info line above the config list
defaultExpiryDays
integer
Default expiry for new configs (0 = no expiry)
dns
object
Your customers' gaming DNS: { enabled, label }. The same "dns" row PUT /servers takes; a key you leave out keeps its value.
subPage
object
Customer subscription-page settings. Keys: tagline, hintText, footerNote, announce and announceUnlimited (notice shown above metered and unlimited subscriptions, up to 200 chars each), showGauge, showStats, showCreatedAt, showRefresh, showHint, showWgNote, showPoweredBy, showV2Pro, expandConfigs, contactLabel, contactUrl, appAndroidUrl, appIosUrl, showQr, showApps, showBurn, tabXrayLabel, tabWgLabel, tabOvpnLabel, tabTgLabel, tabDnsLabel, defaultTab (auto|xray|wireguard|openvpn|tgproxy|dns), hideWg, hideOvpn, hideTgProxy. Only the keys you send change. The gaming DNS on/off and name are not subPage keys: use PUT /servers with id "dns", or dns: { enabled, label } right here.
cURL

curl -X PATCH "https://su.randomatic.ir/api/v1/profile" \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"shopName":"آریانت","brandColor":"#7c3aed","defaultExpiryDays":30,"subPage":{"tabWgLabel":"تانل","hideOvpn":true,"contactUrl":"@arianet_support"}}'
Example response
JSON

{
  "shopName": "آریانت",
  "brandColor": "#7c3aed",
  "accentColor": "",
  "subMode": "auto",
  "logoUrl": "",
  "resalePricePerGb": 12000,
  "infoLineEnabled": true,
  "infoLineTemplate": "",
  "defaultExpiryDays": 30
}
Servers
Custom names and on/off per server

GET
/servers
List servers
#
The online xray, WireGuard and OpenVPN servers available to you, with type, default name and custom name. The list ends with one virtual row, id "dns" (type: dns): the gaming DNS that ships with your customers' metered subscriptions; it takes a name and on/off, never a rank.

cURL

curl -X GET "https://su.randomatic.ir/api/v1/servers" \
  -H "Authorization: Bearer $API_KEY"
Example response
JSON

{
  "servers": [
    {
      "id": "66f2a1…",
      "name": "OpenVPN آلمان",
      "defaultName": "OpenVPN آلمان",
      "customName": "",
      "type": "openvpn",
      "openvpn": true,
      "wireguard": false,
      "online": true,
      "enabled": true,
      "order": 0
    },
    {
      "id": "dns",
      "name": "🇮🇷 DNS (Game, YouTube, Spotify)",
      "defaultName": "🇮🇷 DNS (Game, YouTube, Spotify)",
      "customName": "",
      "type": "dns",
      "dns": true,
      "online": true,
      "enabled": true,
      "order": null
    }
  ]
}
PUT
/servers
Name and order your servers
#
Rename, hide or rank a server across the matching channel of your xray, WireGuard, OpenVPN and unlimited products. Omitted servers keep their saved preference.

Body parameters
prefs
array · required
Array of { nodeId, enabled, customLabel, order }
prefs[].nodeId
string · required
Server id from GET /servers ("dns" for the DNS row)
prefs[].enabled
boolean
Set false to drop it from your customers list
prefs[].customLabel
string
Custom name (max 40 chars). Empty falls back to the default name
prefs[].order
number
Position in the customer list (0 = first). Unranked servers follow the ranked ones. Send null to clear the rank
cURL

curl -X PUT "https://su.randomatic.ir/api/v1/servers" \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"prefs":[{"nodeId":"66f2a1…","enabled":true,"customLabel":"آلمان آریانت","order":0}]}'
Example response
JSON

{
  "servers": [
    {
      "id": "66f2a1…",
      "name": "آلمان آریانت",
      "defaultName": "آلمان",
      "customName": "آلمان آریانت",
      "type": "direct",
      "online": true,
      "enabled": true,
      "order": 0
    }
  ]
}
Metered configs
Create, extend, delete and manage customers

GET
/configs
List configs
#
Active configs by default. Usage on active rows is live.

Query parameters
status
enum
active (default) | disabled | all
limit
integer
Page size, default 100, max 500
page
integer
1-based page number
q
string
Search by customer label
cURL

curl -X GET "https://su.randomatic.ir/api/v1/configs?status=active&limit=100&page=1&q=USER" \
  -H "Authorization: Bearer $API_KEY"
Example response
JSON

{
  "configs": [
    {
      "id": "6a5c…",
      "label": "مشتری احمدی",
      "status": "active",
      "limitGb": 20,
      "usedGb": 5,
      "remainingGb": 15,
      "expiresAt": "2026-08-01T00:00:00Z",
      "note": "",
      "proto": "both",
      "channels": [
        "xray",
        "wireguard",
        "dns"
      ],
      "buyer": null,
      "subUrl": "https://su.randomatic.ir/api/sub/6a5c…"
    }
  ],
  "page": 1,
  "limit": 100,
  "total": 39,
  "hasMore": false
}
POST
/configs
Create a config
#
Creates a config, charges your GB credit (nothing up front in live billing mode; usage is charged as it happens) and returns the subscription link. Customer label is required and unique across your customer history.

Body parameters
gb
number · required
Volume in GB (e.g. 10 or 0.5). You may send trafficMb instead
label
string · required
Required unique customer label, max 40 characters
note
string
Private note, max 500 characters
expiryDays
integer
Expiry in days. Omit it to use your account default
expiryHours
integer
Extra hours on top of the expiry
noExpiry
boolean
Send true for no time limit. expiryDays: 0 alone does not override the account default
proto
enum
Product kind: both (default, xray and WireGuard) | xray (xray links only) | wireguard (WireGuard file only) | openvpn (self-contained .ovpn files only) | dns (the gaming-DNS profile only, no VPN config at all). OpenVPN and DNS both spend the same GB credit. Unknown values normalize to both.
channels
array
Exactly what the customer receives, as an array: xray (direct configs) | wireguard | openvpn | dns. Any combination is allowed, and sending it OVERRIDES proto and dns — combinations such as ["xray","openvpn"] can only be expressed this way. An empty array is refused with no_channels. Omit it and the set is derived from proto and dns.
dns
boolean
Also deliver the gaming-DNS profile alongside whatever `proto` delivers (default false). Implied true when proto=dns. DNS traffic is metered against this config's own allowance. Returns dns_unavailable or dns_off_for_seller — charging nothing — when DNS is off platform-wide or in your own Servers tab.
tgProxyVisible
boolean
Whether this customer's share page offers the Telegram proxy. true shows it, false hides it; omit it to follow your shop-wide sub page setting.
cURL

curl -X POST "https://su.randomatic.ir/api/v1/configs" \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"gb":10,"label":"USER-015","note":"تسویه اول ماه","expiryDays":30,"proto":"both"}'
Example response
JSON

{
  "id": "6a5c…",
  "label": "USER-015",
  "note": "تسویه اول ماه",
  "status": "active",
  "trafficLimitBytes": 10737418240,
  "limitGb": 10,
  "expiresAt": "2026-08-09T12:00:00Z",
  "proto": "both",
  "subUrl": "https://su.randomatic.ir/api/sub/6a5c…",
  "creditGbLeft": 174
}
GET
/configs/finished
Finished configs
#
Configs whose traffic or time ran out, plus the ones you deleted. A separate endpoint from the active list because listing active rows registers them into the live metering path, and a settled config must never go back in there.

Query parameters
limit
integer
Page size, default 60, max 200
skip
integer
How many rows to skip
cURL

curl -X GET "https://su.randomatic.ir/api/v1/configs/finished?limit=100&skip=0" \
  -H "Authorization: Bearer $API_KEY"
Example response
JSON

{
  "configs": [
    {
      "id": "6a5c…",
      "label": "مشتری احمدی",
      "status": "disabled",
      "limitGb": 10,
      "usedGb": 10,
      "remainingGb": 0,
      "disableReason": "traffic_limit",
      "proto": "both",
      "subUrl": "https://su.randomatic.ir/api/sub/6a5c…"
    }
  ],
  "total": 12,
  "hasMore": false
}
GET
/configs/:id
Get one config
#
One config by id.

cURL

curl -X GET "https://su.randomatic.ir/api/v1/configs/ID" \
  -H "Authorization: Bearer $API_KEY"
Example response
JSON

{
  "id": "6a5c…",
  "label": "مشتری احمدی",
  "status": "active",
  "proto": "openvpn",
  "limitGb": 10,
  "usedGb": 2.4,
  "remainingGb": 7.6,
  "note": "",
  "subUrl": "https://su.randomatic.ir/api/sub/6a5c…"
}
GET
/configs/:id/links
Delivery output
#
Returns raw links for xray. For WireGuard and OpenVPN, fetch subUrl with format=json to receive wgConfigs or ovpnConfigs and each file downloadUrl. The subUrl itself is always the primary customer handoff.

cURL

curl -X GET "https://su.randomatic.ir/api/v1/configs/ID/links" \
  -H "Authorization: Bearer $API_KEY"
Example response
JSON

{
  "id": "6a5c…",
  "proto": "openvpn",
  "subUrl": "https://su.randomatic.ir/api/sub/6a5c…",
  "links": [],
  "protocolFiles": {
    "json": "https://su.randomatic.ir/api/sub/6a5c…?format=json",
    "openvpnPage": "https://su.randomatic.ir/api/sub/6a5c…/ovpn"
  }
}
POST
/configs/:id/extend
Extend a config
#
Add volume, add days, or both. At least one of gb / days is required. Volume is charged to your credit, days are free.

Body parameters
gb
number
GB to add
days
integer
Days to add, from the current expiry or now, whichever is later
cURL

curl -X POST "https://su.randomatic.ir/api/v1/configs/ID/extend" \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"gb":5,"days":30}'
Example response
JSON

{
  "id": "6a5c…",
  "trafficLimitBytes": 16106127360,
  "limitGb": 15,
  "expiresAt": "2026-09-08T12:00:00Z",
  "creditGbLeft": 169
}
GET
/configs/:id/reset-traffic/quote
Quote a traffic reset
#
States exactly what a reset costs before you run it. On a prepaid account the cost is the volume used since the last reset, taken from your credit; in live billing nothing is taken now (chargedAsUsed: true) and the new headroom is charged as it is used. When allowed is false, message says why.

cURL

curl -X GET "https://su.randomatic.ir/api/v1/configs/ID/reset-traffic/quote" \
  -H "Authorization: Bearer $API_KEY"
Example response
JSON

{
  "id": "6a5c…",
  "mode": "reset",
  "billing": "prepaid",
  "usedBytes": 7516192768,
  "sizeBytes": 10737418240,
  "costBytes": 7516192768,
  "costGb": 7,
  "chargedAsUsed": false,
  "creditBytes": 182536110080,
  "allowed": true,
  "reason": null,
  "message": null
}
POST
/configs/:id/reset-traffic
Reset traffic
#
Sets the customer usage back to zero with the full size available again; the link does not change. It costs what the quote said. trafficLimitBytes grows because meters are cumulative; usageResetBytes is the usage level at the reset, and the customer sees usage from there; limitGb and usedGb always report what the customer sees (since the last reset). On a finished (used-up) config it reactivates the same link with its full size.

cURL

curl -X POST "https://su.randomatic.ir/api/v1/configs/ID/reset-traffic" \
  -H "Authorization: Bearer $API_KEY"
Example response
JSON

{
  "id": "6a5c…",
  "trafficLimitBytes": 18253611008,
  "limitGb": 10,
  "usageResetBytes": 7516192768,
  "expiresAt": "2026-10-20T12:00:00Z",
  "costBytes": 7516192768,
  "costGb": 7,
  "chargedAsUsed": false,
  "creditGbLeft": 163
}
PUT
/configs/:id/expiry
Set the expiry
#
Sets the expiry to N days from now, or removes it (no expiry). Free. To add or remove days relative to the current expiry use extend.

Body parameters
days
integer
Days from now (1 to 3650)
noExpiry
boolean
Send true for no expiry; days is then ignored
cURL

curl -X PUT "https://su.randomatic.ir/api/v1/configs/ID/expiry" \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"noExpiry":true}'
Example response
JSON

{
  "id": "6a5c…",
  "expiresAt": null,
  "noExpiry": true
}
PUT
/configs/:id/tg-proxy
Telegram proxy for this customer
#
Decides whether this customer's share page offers the Telegram proxy. true and false override your shop-wide setting for this customer only; null returns to the shop setting. Hiding it from a customer who had switched it on also switches it off (revoked: true).

Body parameters
visible
boolean · required
true | false | null
cURL

curl -X PUT "https://su.randomatic.ir/api/v1/configs/ID/tg-proxy" \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"visible":false}'
Example response
JSON

{
  "id": "6a5c…",
  "tgProxyVisible": false,
  "hidden": true,
  "revoked": false
}
POST
/configs/:id/revive
Revive a finished config
#
Reactivates a finished or deleted config with the same id and customer link. gb is the new total balance, not an increment; the server charges only the portion not already covered by its final settlement.

Body parameters
gb
number · required
New total GB balance; balanceMb is also accepted
days
integer
Optional fresh validity days
cURL

curl -X POST "https://su.randomatic.ir/api/v1/configs/ID/revive" \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"gb":20,"days":30}'
Example response
JSON

{
  "id": "6a5c…",
  "status": "active",
  "trafficLimitBytes": 21474836480,
  "limitGb": 20,
  "trafficUsedBytes": 0,
  "expiresAt": "2026-10-13T12:00:00Z",
  "chargedBytes": 17179869184,
  "creditGb": 168,
  "subUrl": "https://su.randomatic.ir/api/sub/6a5c…"
}
PUT
/configs/:id/label
Rename a config
#
Changes the config name. Display only: the customer subscription link, volume and usage are all untouched, so there is no need to delete and recreate it.

Body parameters
label
string · required
Required unique name, max 40 characters
cURL

curl -X PUT "https://su.randomatic.ir/api/v1/configs/ID/label" \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"label":"مشتری احمدی (تمدید شد)"}'
Example response
JSON

{
  "id": "6a5c…",
  "label": "مشتری احمدی (تمدید شد)"
}
PUT
/configs/:id/note
Customer note
#
Your private note about this customer. They never see it.

Body parameters
note
string · required
Note text (max 500 chars)
cURL

curl -X PUT "https://su.randomatic.ir/api/v1/configs/ID/note" \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"note":"تمدید ماهانه، پرداخت کارت به کارت"}'
Example response
JSON

{
  "id": "6a5c…",
  "note": "تمدید ماهانه، پرداخت کارت به کارت"
}
GET
/configs/:id/servers
One customer servers
#
The server list for this one customer, with their own name and visibility overrides.

cURL

curl -X GET "https://su.randomatic.ir/api/v1/configs/ID/servers" \
  -H "Authorization: Bearer $API_KEY"
Example response
JSON

{
  "servers": [
    {
      "key": "dn:66f2a1…",
      "name": "آلمان آریانت",
      "defaultName": "آلمان آریانت",
      "customName": "",
      "hidden": false
    }
  ]
}
PUT
/configs/:id/servers
Override one server
#
Rename or hide one server for this one customer. It never affects metering.

Body parameters
key
string · required
Server key from GET /configs/:id/servers
hidden
boolean
Hide it from this customer
label
string
Name for this customer only. Empty falls back to your shop-wide name
cURL

curl -X PUT "https://su.randomatic.ir/api/v1/configs/ID/servers" \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"key":"dn:66f2a1…","label":"سرور ویژه","hidden":false}'
Example response
JSON

{
  "servers": [
    {
      "key": "dn:66f2a1…",
      "name": "سرور ویژه",
      "defaultName": "آلمان آریانت",
      "customName": "سرور ویژه",
      "hidden": false
    }
  ]
}
POST
/configs/:id/pause
Pause a config
#
Switches a customer off temporarily. No refund and no new link: resume brings the same config back with the same balance, expiry and subscription URL. For a permanent, refunding removal use DELETE.

cURL

curl -X POST "https://su.randomatic.ir/api/v1/configs/ID/pause" \
  -H "Authorization: Bearer $API_KEY"
Example response
JSON

{
  "id": "6a5c…",
  "status": "paused"
}
POST
/configs/:id/resume
Resume a config
#
Puts a paused config back on the wire, unchanged.

cURL

curl -X POST "https://su.randomatic.ir/api/v1/configs/ID/resume" \
  -H "Authorization: Bearer $API_KEY"
Example response
JSON

{
  "id": "6a5c…",
  "status": "active"
}
POST
/configs/:id/rotate-link
Rotate the subscription link
#
Kills the customer current link and hands back a new one carrying the same remaining volume and days. Irreversible: every device on the old link stops immediately. The id changes too, so take the new id and subUrl from this response.

cURL

curl -X POST "https://su.randomatic.ir/api/v1/configs/ID/rotate-link" \
  -H "Authorization: Bearer $API_KEY"
Example response
JSON

{
  "id": "7c9e…",
  "oldId": "6a5c…",
  "subUrl": "https://su.randomatic.ir/api/sub/7c9e…",
  "expiresAt": "2026-09-08T12:00:00Z"
}
DELETE
/configs/:id
Delete a config
#
Disables the config and refunds its unused volume (limit − final usage) to your credit immediately. The response states the refund and your new balance, so you never have to infer either or call /wallet again; refundedBytes: 0 means the config had spent its quota and there was nothing left to return (or your account bills live, so nothing was taken up front).

cURL

curl -X DELETE "https://su.randomatic.ir/api/v1/configs/ID" \
  -H "Authorization: Bearer $API_KEY"
Example response
JSON

{
  "ok": true,
  "refundedBytes": 3221225472,
  "refundedGb": 3,
  "creditBytes": 168894136320,
  "creditGb": 157
}
Telegram proxy
Minting and extending proxies, paid from your GB credit

GET
/proxy/wallet
Proxy credit
#
Proxies are paid from the SAME GB credit as configs, byte for byte and at the same price (they had a wallet of their own until 2026-09-04). Returns your balance, the price per GB, the min/max size of one proxy and the endpoint your customer connects to. `proxyBytes` is that one balance under its old name. A null endpoint means no live node can serve a proxy right now, so do not sell against it.

cURL

curl -X GET "https://su.randomatic.ir/api/v1/proxy/wallet" \
  -H "Authorization: Bearer $API_KEY"
Example response
JSON

{
  "proxyBytes": 214748364800,
  "proxyGb": 200,
  "pricePerGbToman": 4000,
  "minTopupGb": 200,
  "minGb": 20,
  "maxGb": 200,
  "maxPerSeller": 500,
  "activeCount": 7,
  "maxDays": 90,
  "endpoint": {
    "host": "1.2.3.4",
    "port": 443
  }
}
POST
/proxy/topup
Top up proxy credit (gone)
#
There is no separate proxy wallet any more. This returns 410; use POST /wallet/topup instead. Deliberately not forwarded: the two wallets had different prices AND different minimums, so forwarding would spend four times what your last call spent without anyone deciding to.

cURL

curl -X POST "https://su.randomatic.ir/api/v1/proxy/topup" \
  -H "Authorization: Bearer $API_KEY"
Example response
JSON

{
  "error": "wallet_merged",
  "message": "اعتبار پروکسی در اعتبار فروشندگی ادغام شد. برای شارژ از POST /wallet/topup استفاده کن."
}
GET
/proxies
List proxies
#
The proxies you minted, with their username, password and a ready t.me/socks link. firstConnection stays false until the customer has connected even once, which is normal for stock you have not handed out yet.

Query parameters
status
enum
active (default) | all
cURL

curl -X GET "https://su.randomatic.ir/api/v1/proxies?status=active" \
  -H "Authorization: Bearer $API_KEY"
Example response
JSON

{
  "proxies": [
    {
      "id": "8f2b…",
      "label": "مشتری احمدی",
      "status": "active",
      "username": "a1b2c3_ff00",
      "password": "d4e5…",
      "limitGb": 20,
      "usedGb": 3.1,
      "remainingGb": 16.9,
      "expiresAt": "2026-12-01T00:00:00Z",
      "firstConnection": true,
      "server": "1.2.3.4",
      "port": 443,
      "link": "https://t.me/socks?server=1.2.3.4&port=443&user=a1b2c3_ff00&pass=d4e5…"
    }
  ],
  "endpoint": {
    "host": "1.2.3.4",
    "port": 443
  }
}
POST
/proxies
Create a proxy
#
Mints one Telegram proxy, charged to your GB credit. Customer label is required and unique across your customer history.

Body parameters
gb
number · required
Volume in GB. You may send trafficMb instead
label
string · required
Required unique customer label
note
string
Private note
cURL

curl -X POST "https://su.randomatic.ir/api/v1/proxies" \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"gb":20,"label":"مشتری احمدی"}'
Example response
JSON

{
  "id": "8f2b…",
  "label": "مشتری احمدی",
  "status": "active",
  "username": "a1b2c3_ff00",
  "password": "d4e5…",
  "limitGb": 20,
  "usedGb": 0,
  "expiresAt": "2026-12-01T00:00:00Z",
  "server": "1.2.3.4",
  "port": 443,
  "link": "https://t.me/socks?server=1.2.3.4&port=443&user=a1b2c3_ff00&pass=d4e5…",
  "proxyGbLeft": 180
}
POST
/proxies/:id/extend
Extend a proxy
#
Adds volume and/or days to the SAME proxy. The username and password never change, which is the whole point: the customer saved them inside Telegram on every device. gb is charged to your GB credit; days are free, extend from the current expiry or now (whichever is later) and are capped a year out.

Body parameters
gb
number
GB to add
days
integer
Days to add
cURL

curl -X POST "https://su.randomatic.ir/api/v1/proxies/ID/extend" \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"gb":20,"days":30}'
Example response
JSON

{
  "id": "8f2b…",
  "trafficLimitBytes": 42949672960,
  "limitGb": 40,
  "usedGb": 3.1,
  "expiresAt": "2026-12-31T00:00:00Z",
  "proxyGbLeft": 160
}
DELETE
/proxies/:id
Delete a proxy
#
Disables the proxy and refunds its unused volume to your GB credit.

cURL

curl -X DELETE "https://su.randomatic.ir/api/v1/proxies/ID" \
  -H "Authorization: Bearer $API_KEY"
Example response
JSON

{
  "ok": true,
  "proxyGbLeft": 180
}
Unlimited plans
Buy at wholesale, resell to customers

GET
/unlimited/status
Unlimited pricing
#
Wholesale price per device tier, plan length, and whether it is on sale right now. For day-term («نامحدود روزانه») sales, `dayPrices` gives the price of every term from minDays to maxDays and `unitCosts` what each term spends from your stock; read them instead of re-deriving the curve.

cURL

curl -X GET "https://su.randomatic.ir/api/v1/unlimited/status" \
  -H "Authorization: Bearer $API_KEY"
Example response
JSON

{
  "enabled": true,
  "days": 30,
  "defaultDevices": 2,
  "maxActive": 0,
  "options": [
    {
      "devices": 1,
      "priceToman": 140000,
      "priceVp": 35,
      "minDays": 1,
      "maxDays": 90,
      "dayPrices": {
        "1": 8000,
        "7": 40000,
        "30": 140000,
        "90": 371000
      },
      "unitCosts": {
        "1": 0.0571,
        "7": 0.2857,
        "30": 1,
        "90": 2.65
      }
    },
    {
      "devices": 2,
      "priceToman": 280000,
      "priceVp": 70,
      "minDays": 1,
      "maxDays": 90,
      "dayPrices": {
        "1": 16000,
        "7": 79000,
        "30": 280000,
        "90": 742000
      },
      "unitCosts": {
        "1": 0.1143,
        "7": 0.5643,
        "30": 2,
        "90": 5.3
      }
    },
    {
      "devices": 3,
      "priceToman": 420000,
      "priceVp": 105,
      "minDays": 1,
      "maxDays": 90,
      "dayPrices": {
        "1": 21000,
        "7": 117000,
        "30": 420000,
        "90": 1104000
      },
      "unitCosts": {
        "1": 0.15,
        "7": 0.8358,
        "30": 3,
        "90": 7.8858
      }
    }
  ],
  "priceToman": 280000,
  "priceVp": 70,
  "wallet": {
    "sellerUnlimitedUnits": 12.5,
    "minTopupUnits": 2,
    "listTomanEach": 140000,
    "saleOffPercent": 0,
    "convertOutTomanEach": 119000,
    "brackets": [
      {
        "units": 10,
        "discountPercent": 5,
        "tomanTotal": 1330000,
        "tomanEach": 133000,
        "vp": 333
      }
    ]
  }
}
GET
/unlimited/units/quote
Quote unlimited stock
#
Returns the exact batch-stock price with quantity discount and account-specific pricing. Show this before purchase and do not rederive it.

Query parameters
units
integer · required
Number of stock units
cURL

curl -X GET "https://su.randomatic.ir/api/v1/unlimited/units/quote?units=10" \
  -H "Authorization: Bearer $API_KEY"
Example response
JSON

{
  "success": true,
  "units": 10,
  "toman": 1330000,
  "tomanEach": 140000,
  "effectiveTomanEach": 133000,
  "discountPercent": 5,
  "vp": 333,
  "pricePerVp": 4000
}
POST
/unlimited/units/topup
Buy unlimited stock
#
Buys stock units with VPoints at the current price and returns the new stock balance. Quote first; this is a real purchase with no automatic undo.

Body parameters
units
integer · required
Number of stock units
cURL

curl -X POST "https://su.randomatic.ir/api/v1/unlimited/units/topup" \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"units":10}'
Example response
JSON

{
  "success": true,
  "units": 10,
  "vpSpent": 333,
  "tomanTotal": 1330000,
  "tomanEach": 133000,
  "qtyDiscountPercent": 5,
  "sellerUnlimitedUnits": 22.5,
  "newBalance": 709
}
GET
/unlimited
List unlimited plans
#
Your active and paused unlimited plans. status is authoritative; subUrl remains the customer link, but a paused plan serves nothing until resumed.

cURL

curl -X GET "https://su.randomatic.ir/api/v1/unlimited" \
  -H "Authorization: Bearer $API_KEY"
Example response
JSON

{
  "configs": [
    {
      "id": "7b1d…",
      "label": "مشتری رضایی",
      "unlimited": true,
      "status": "active",
      "devices": 2,
      "expiresAt": "2026-08-26T00:00:00Z",
      "daysLeft": 30,
      "locked": false,
      "lockMinutesLeft": 0,
      "subUrl": "https://su.randomatic.ir/api/sub/7b1d…"
    }
  ]
}
POST
/unlimited
Buy an unlimited plan
#
Buys one plan at wholesale. Customer label is required and unique across your customer history. Paid from unlimited stock first, then VPoints.

Body parameters
label
string · required
Required unique customer label
note
string
Private note
devices
integer
Concurrent device tier, from options in /unlimited/status
days
integer
Term in days, from dayPrices in /unlimited/status. Omit for 30 days.
cURL

curl -X POST "https://su.randomatic.ir/api/v1/unlimited" \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"label":"مشتری رضایی","devices":2,"days":7}'
Example response
JSON

{
  "id": "7b1d…",
  "label": "مشتری رضایی",
  "unlimited": true,
  "devices": 2,
  "priceToman": 280000,
  "vpPaid": 70,
  "vpBalance": 972,
  "expiresAt": "2026-08-26T00:00:00Z",
  "subUrl": "https://su.randomatic.ir/api/sub/7b1d…",
  "links": [
    {
      "name": "آلمان آریانت",
      "uri": "vless://…"
    }
  ]
}
PUT
/unlimited/:id/label
Rename an unlimited plan
#
Changes the plan name. Display-only: the customer link and the plan clock are untouched.

Body parameters
label
string · required
Required unique name, max 40 characters
cURL

curl -X PUT "https://su.randomatic.ir/api/v1/unlimited/ID/label" \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"label":"مشتری رضایی"}'
Example response
JSON

{
  "id": "7b1d…",
  "label": "مشتری رضایی"
}
PUT
/unlimited/:id/note
Unlimited private note
#
Updates the seller private note, or clears it with an empty string. The customer never sees it.

Body parameters
note
string · required
Private note, max 500 characters
cURL

curl -X PUT "https://su.randomatic.ir/api/v1/unlimited/ID/note" \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"note":"تسویه اول ماه"}'
Example response
JSON

{
  "id": "7b1d…",
  "note": "تسویه اول ماه"
}
POST
/unlimited/:id/pause
Pause unlimited
#
Temporarily removes the plan from every serving plane without changing its link, term, device tier or balances. Nothing is refunded.

cURL

curl -X POST "https://su.randomatic.ir/api/v1/unlimited/ID/pause" \
  -H "Authorization: Bearer $API_KEY"
Example response
JSON

{
  "id": "7b1d…",
  "status": "paused",
  "trafficUsedBytes": 1073741824
}
POST
/unlimited/:id/resume
Resume unlimited
#
Restores the same paused plan with the same link, term and device tier.

cURL

curl -X POST "https://su.randomatic.ir/api/v1/unlimited/ID/resume" \
  -H "Authorization: Bearer $API_KEY"
Example response
JSON

{
  "id": "7b1d…",
  "status": "active"
}
POST
/unlimited/:id/rotate-link
Rotate unlimited link
#
Irreversibly kills the old link immediately and returns a new id and subUrl. Remaining term and device tier are preserved; every device using the old link disconnects.

cURL

curl -X POST "https://su.randomatic.ir/api/v1/unlimited/ID/rotate-link" \
  -H "Authorization: Bearer $API_KEY"
Example response
JSON

{
  "id": "9d2e…",
  "oldId": "7b1d…",
  "subUrl": "https://su.randomatic.ir/api/sub/9d2e…",
  "expiresAt": "2026-08-26T00:00:00Z"
}
DELETE
/unlimited/:id
Delete unlimited
#
Permanently cancels a resale plan and returns the server-calculated unused term, counted in whole hours, to unlimited stock. The response states the exact refund and new balance.

cURL

curl -X DELETE "https://su.randomatic.ir/api/v1/unlimited/ID" \
  -H "Authorization: Bearer $API_KEY"
Example response
JSON

{
  "ok": true,
  "id": "7b1d…",
  "unitsRefunded": 0.7,
  "daysRemaining": 21,
  "totalDays": 30,
  "unlimitedUnits": 13.2
}
GET
/unlimited/:id
Get one plan
#
One plan with its raw config links.

cURL

curl -X GET "https://su.randomatic.ir/api/v1/unlimited/ID" \
  -H "Authorization: Bearer $API_KEY"
Example response
JSON

{
  "id": "7b1d…",
  "label": "مشتری رضایی",
  "unlimited": true,
  "devices": 2,
  "daysLeft": 21,
  "subUrl": "https://su.randomatic.ir/api/sub/7b1d…",
  "links": [
    {
      "name": "آلمان آریانت",
      "uri": "vless://…"
    }
  ]
}
Panel resale and sub-sellers
Full sub-seller management: prices, panel addresses and credit

GET
/resale
Panel resale overview
#
Your resale settings, panel addresses, our prices for sub-sellers and an earnings summary.

cURL

curl -X GET "https://su.randomatic.ir/api/v1/resale" \
  -H "Authorization: Bearer $API_KEY"
Example response
JSON

{
  "settings": {
    "enabled": true,
    "mode": "price",
    "percent": 15,
    "gbToman": 2900,
    "unitToman": 160000,
    "payOnline": true,
    "payCard": false,
    "paySupport": true,
    "supportUrl": "@mysupport"
  },
  "ourPrices": {
    "gbToman": 2000,
    "unitToman": 140000,
    "myGbToman": 1900
  },
  "maxPercent": 50
}
PUT
/resale
Update resale settings
#
Send only the fields to change: enabled, mode (percent or price), percent, gbToman, unitToman, payOnline, payCard, cardNumber, cardHolder, paySupport, supportUrl, signupOpen, welcome, minTopupGb, entryMinTopupGb, unlimitedMinUnits. The last three are your sub-sellers' purchase minimums (restock GB, first-purchase GB, unlimited units); 0 means ours, and the overview's ourMins and minFloors show how low you may go. Setting a first-purchase minimum also lifts our first-purchase Toman floor for your sub-sellers. At least one payment method must stay on.

cURL

curl -X PUT "https://su.randomatic.ir/api/v1/resale" \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"mode":"price","gbToman":2900,"unitToman":160000}'
Example response
JSON

{
  "ok": true,
  "settings": {
    "mode": "price",
    "gbToman": 2900,
    "unitToman": 160000
  }
}
POST
/resale/domains
Add a panel address
#
A bare name (becomes name.vslshop.ir) or your own full domain. Up to 3.

cURL

curl -X POST "https://su.randomatic.ir/api/v1/resale/domains" \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"domain":"myshop"}'
Example response
JSON

{
  "ok": true,
  "domain": "myshop.vslshop.ir",
  "managed": true,
  "domains": [
    {
      "domain": "myshop.vslshop.ir",
      "status": "active"
    }
  ]
}
POST
/resale/domains/verify
Verify a domain
#
Re-checks a custom domain after you set its A record. If it is not ready yet, message says why.

cURL

curl -X POST "https://su.randomatic.ir/api/v1/resale/domains/verify" \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"domain":"panel.example.com"}'
Example response
JSON

{
  "ok": true,
  "domainStatus": "active",
  "domains": [
    {
      "domain": "panel.example.com",
      "status": "active"
    }
  ]
}
PUT
/resale/sub-domain
Sub-sellers' domain
#
Your own domain (e.g. example.com) so each sub-seller's site lives at <slug>.example.com instead of vslshop.ir. Point a wildcard A record (*) at our server first. One per account; a new one replaces the old. Old vslshop.ir links keep working.

cURL

curl -X PUT "https://su.randomatic.ir/api/v1/resale/sub-domain" \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"domain":"example.com"}'
Example response
JSON

{
  "ok": true,
  "verify": {
    "ok": true,
    "status": "active"
  },
  "subDomain": {
    "domain": "example.com",
    "status": "active"
  }
}
POST
/resale/sub-domain/verify
Verify the sub-sellers' domain
#
Re-checks the wildcard record. Once active, every sub-seller's site address moves onto it.

cURL

curl -X POST "https://su.randomatic.ir/api/v1/resale/sub-domain/verify" \
  -H "Authorization: Bearer $API_KEY"
Example response
JSON

{
  "ok": true,
  "subDomain": {
    "domain": "example.com",
    "status": "active"
  }
}
DELETE
/resale/sub-domain
Remove the sub-sellers' domain
#
Sub-sellers' site addresses go back to vslshop.ir, and links they handed out on this domain stop working.

cURL

curl -X DELETE "https://su.randomatic.ir/api/v1/resale/sub-domain" \
  -H "Authorization: Bearer $API_KEY"
Example response
JSON

{
  "ok": true,
  "subDomain": null
}
DELETE
/resale/domains
Remove a panel address
#
Removes a panel address. Send domain in the body or the query.

cURL

curl -X DELETE "https://su.randomatic.ir/api/v1/resale/domains" \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"domain":"myshop.vslshop.ir"}'
Example response
JSON

{
  "ok": true
}
GET
/resale/subs
List sub-sellers
#
Filter with q (name), status (active or suspended), sort (recent, commission, active), skip and limit.

cURL

curl -X GET "https://su.randomatic.ir/api/v1/resale/subs" \
  -H "Authorization: Bearer $API_KEY"
Example response
JSON

{
  "rows": [
    {
      "userId": "1746326",
      "displayName": "ali_shop",
      "status": "active",
      "percent": 15,
      "percentOverride": null,
      "modeOverride": "price",
      "gbTomanOverride": 2500,
      "unitTomanOverride": 0,
      "creditGb": 120.5,
      "unlimitedUnits": 3,
      "paidToman": 900000,
      "commissionToman": 135000,
      "purchases": 4
    }
  ],
  "total": 1,
  "skip": 0,
  "limit": 50
}
POST
/resale/subs
Create a sub-seller
#
Creates a sub-seller account with a username and password. percentOverride and note are optional. Returns your panel link to send them.

cURL

curl -X POST "https://su.randomatic.ir/api/v1/resale/subs" \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"username":"ali_shop","password":"Secret123","note":"از کانال"}'
Example response
JSON

{
  "ok": true,
  "userId": "1746326",
  "displayName": "ali_shop",
  "panelUrl": "https://myshop.vslshop.ir"
}
GET
/resale/subs/:userId
Get one sub-seller
#
One sub-seller: their balances, purchases and your earnings from them.

cURL

curl -X GET "https://su.randomatic.ir/api/v1/resale/subs/:userId" \
  -H "Authorization: Bearer $API_KEY"
Example response
JSON

{
  "sub": {
    "userId": "1746326",
    "displayName": "ali_shop",
    "status": "active",
    "percent": 15,
    "percentOverride": null,
    "modeOverride": "price",
    "gbTomanOverride": 2500,
    "unitTomanOverride": 0,
    "creditGb": 120.5,
    "unlimitedUnits": 3,
    "paidToman": 900000,
    "commissionToman": 135000,
    "purchases": 4
  }
}
PATCH
/resale/subs/:userId
Update a sub-seller
#
status (active or suspended), note, and a custom price: modeOverride (percent, price, or null for your default), percentOverride, gbTomanOverride, unitTomanOverride. A price of 0 means your default.

cURL

curl -X PATCH "https://su.randomatic.ir/api/v1/resale/subs/:userId" \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"modeOverride":"price","gbTomanOverride":2500}'
Example response
JSON

{
  "ok": true,
  "sub": {
    "userId": "1746326",
    "displayName": "ali_shop",
    "status": "active",
    "percent": 15,
    "percentOverride": null,
    "modeOverride": "price",
    "gbTomanOverride": 2500,
    "unitTomanOverride": 0,
    "creditGb": 120.5,
    "unlimitedUnits": 3,
    "paidToman": 900000,
    "commissionToman": 135000,
    "purchases": 4
  }
}
POST
/resale/subs/:userId/password
Reset sub-seller password
#
Sets a new password on an account you created (at least 6 characters).

cURL

curl -X POST "https://su.randomatic.ir/api/v1/resale/subs/:userId/password" \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"password":"NewSecret1"}'
Example response
JSON

{
  "ok": true
}
POST
/resale/subs/:userId/transfer
Transfer credit
#
Gives your own credit to a sub-seller (give) or takes it back (take). Send gb and/or units; priceToman and note are optional and only recorded.

cURL

curl -X POST "https://su.randomatic.ir/api/v1/resale/subs/:userId/transfer" \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"direction":"give","gb":50,"units":0,"priceToman":145000}'
Example response
JSON

{
  "ok": true,
  "subCreditGb": 170.5,
  "subUnits": 3
}
GET
/resale/transfers
Transfer history
#
Filter with childId, before and limit.

cURL

curl -X GET "https://su.randomatic.ir/api/v1/resale/transfers" \
  -H "Authorization: Bearer $API_KEY"
Example response
JSON

{
  "rows": [
    {
      "id": "6b11…",
      "at": "2026-10-14T12:00:00Z",
      "childId": "1746326",
      "displayName": "ali_shop",
      "gb": 50,
      "units": 0,
      "priceToman": 145000,
      "note": "",
      "fromRequest": false
    }
  ],
  "next": null
}
GET
/resale/commissions
Earnings log
#
What you earned from sub-sellers buying online. Filter with childId, before and limit.

cURL

curl -X GET "https://su.randomatic.ir/api/v1/resale/commissions" \
  -H "Authorization: Bearer $API_KEY"
Example response
JSON

{
  "rows": [
    {
      "id": "6b12…",
      "at": "2026-10-14T12:00:00Z",
      "childId": "1746326",
      "displayName": "ali_shop",
      "kind": "credit",
      "paidToman": 290000,
      "commissionToman": 90000,
      "percent": 31
    }
  ],
  "next": null
}
GET
/resale/requests
Credit requests
#
Requests sub-sellers sent after paying you by card. status: pending (default), approved, rejected, cancelled or all.

cURL

curl -X GET "https://su.randomatic.ir/api/v1/resale/requests" \
  -H "Authorization: Bearer $API_KEY"
Example response
JSON

{
  "rows": [
    {
      "id": "6b0f…",
      "at": "2026-10-14T12:00:00Z",
      "childId": "1746326",
      "displayName": "ali_shop",
      "gb": 100,
      "units": 0,
      "amountToman": 290000,
      "note": "",
      "status": "pending"
    }
  ]
}
POST
/resale/requests/:id
Approve or reject a request
#
action: approve (credit moves from you to the sub-seller) or reject. note is optional.

cURL

curl -X POST "https://su.randomatic.ir/api/v1/resale/requests/ID" \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"action":"approve"}'
Example response
JSON

{
  "ok": true
}
GET
/panel-credit
My credit requests (sub-seller)
#
For a sub-seller account: the credit requests you sent your reseller.

cURL

curl -X GET "https://su.randomatic.ir/api/v1/panel-credit" \
  -H "Authorization: Bearer $API_KEY"
Example response
JSON

{
  "rows": [
    {
      "id": "6b0f…",
      "gb": 100,
      "units": 0,
      "amountToman": 290000,
      "status": "pending"
    }
  ]
}
POST
/panel-credit
Request credit (sub-seller)
#
After paying your reseller, ask for the credit: gb and/or units, amountToman and note.

cURL

curl -X POST "https://su.randomatic.ir/api/v1/panel-credit" \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"gb":100,"amountToman":290000,"note":"کارت به کارت ساعت 14"}'
Example response
JSON

{
  "ok": true,
  "request": {
    "id": "6b0f…",
    "gb": 100,
    "units": 0,
    "amountToman": 290000,
    "status": "pending"
  }
}
DELETE
/panel-credit/:id
Cancel a request (sub-seller)
#
Cancels a request that has not been decided yet.

cURL

curl -X DELETE "https://su.randomatic.ir/api/v1/panel-credit/ID" \
  -H "Authorization: Bearer $API_KEY"
Example response
JSON

{
  "ok": true
}
Stats
Sales, usage and profit

GET
/stats
Sales stats
#
Sales, usage, cost and profit, plus a daily trend and your last 100 configs.

cURL

curl -X GET "https://su.randomatic.ir/api/v1/stats" \
  -H "Authorization: Bearer $API_KEY"
Example response
JSON

{
  "configsTotal": 47,
  "configsActive": 39,
  "provisionedGb": 520,
  "deliveredGb": 310,
  "walletGb": 184,
  "costTomanPerGb": 4000,
  "resalePricePerGb": 12000,
  "costToman": 2080000,
  "revenueToman": 6240000,
  "profitToman": 4160000,
  "trend": [
    {
      "date": "2026-07-26",
      "count": 3
    }
  ]
}
Error codes
Every error comes back in this shape: { "error": "...", "message": "..." }

400
bad_request
Invalid input; the message field says exactly what is wrong
400
label_required
Customer label is required for manual creation
409
label_duplicate
This label already exists in your customer history
401
unauthorized / invalid_key
Missing key, or the key was revoked
403
not_a_seller
Not a seller account; unlimited plans are seller-only
400
proto_unavailable
New sales for this protocol, normally OpenVPN, are temporarily operator-disabled; no credit was charged
400
dns_unavailable
The gaming-DNS profile is not enabled on the platform right now; no credit was charged
400
no_channels
An empty `channels` array was sent; it must name at least one channel. No credit was charged
400
dns_off_for_seller
DNS is switched off in your own Servers tab; turn it on first. No credit was charged
400
conversion_unavailable / conversion_failed
The conversion route or amount is not allowed, or the source balance is insufficient
400
pause_failed / resume_failed / rotate_failed / delete_failed
The customer-management action failed; message explains the exact state
404
not_found
Config or plan not found, or not yours
429
rate_limited
More than 120 requests in a minute
500
server_error
Our side failed; retry later
These docs are generated from the service itself, so they always match what the API actually serves.