# Ora de Mate Online — cod deployabil

Tema child WordPress (`teomate-child/`, părinte: Kadence) pentru orademateonline.ro.

**Fluxul de deploy:** modificări locale → commit → push pe GitHub → cPanel Git™
Version Control trage modificările (Update from Remote) → `.cpanel.yml` copiază
tema în `public_html/wp-content/themes/teomate-child`.

Acest repo conține DOAR cod care ajunge pe server — fără documente de business,
fără secrete, fără date de clienți. Documentația proiectului trăiește separat,
în repo-ul privat local `D:\TEO Mate`.
