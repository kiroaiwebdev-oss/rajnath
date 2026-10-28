# LiveKit (in-app audio/video calls) on your own VPS

LiveKit server is open source (Apache-2.0) and runs on an ordinary Linux VPS. It can share a box with the website, but a separate small VPS is better.

## Requirements
- A VPS with its **own public IPv4** and root access (Docker). Shared hosting or cPanel will not work. A VPS behind carrier-grade NAT will not work either.
- A subdomain such as `rtc.astrorajnathjha.com` with a trusted TLS certificate. Caddy obtains one automatically; self-signed certificates do not work.
- Firewall ports:
  - **443/TCP**: signalling (WebSocket) through the reverse proxy.
  - **7881/TCP**: media fallback when UDP is blocked.
  - **7882/UDP**: media, as a single multiplexed port. A range such as 50000–60000/UDP also works.
- For customers on office or hotel Wi-Fi that blocks UDP: enable LiveKit's built-in TURN on 443/5349 (TLS) using a second subdomain.
- Size:
  - 2 vCPU / 4 GB RAM is plenty for a few simultaneous one-to-one calls.
  - Bandwidth per call: about 0.1 Mbps for audio, 1–2 Mbps per person for video.

## What we need from you to connect it
1. A VPS that meets the requirements above, and a DNS record pointing to it.
2. Permission to open the ports above (in some providers' panels these are "security groups").

After that we add a `livekit` service to `deploy/docker-compose.yml` and the API key and secret to Admin settings. The website and app then get short-lived room tokens for each booking. Join is allowed only within ±15 minutes of the slot, and only for that customer and that astrologer.
