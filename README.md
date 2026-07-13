# Tracsoft AI Lead Qualifier

AI-assisted lead qualification chatbot for Tracsoft.com.

## Install from GitHub

1. Open the latest release at `https://github.com/tracsoftllc/tracsoft-ai-lead-qualifier/releases`.
2. Download the release ZIP.
3. In WordPress, go to **Plugins > Add New Plugin > Upload Plugin**.
4. Upload the ZIP, install, and activate.

## Publish an Update

1. Update the plugin code.
2. Bump both version values in `tracsoft-ai-lead-qualifier.php`:
   - Plugin header `Version`
   - `TRACSOFT_LB_VERSION`
3. Commit and push to `main`.
4. Create a ZIP with `tracsoft-ai-lead-qualifier/` as the top-level folder.
5. Create a matching GitHub release and upload the ZIP as `tracsoft-ai-lead-qualifier.zip`, for example:

```bash
git tag v1.0.4
git push origin main --tags
zip -r /tmp/tracsoft-ai-lead-qualifier.zip tracsoft-ai-lead-qualifier
```

WordPress sites with the plugin installed will check the public GitHub repo for the latest release asset or tag and show an available plugin update when the tag version is newer than the installed version.
