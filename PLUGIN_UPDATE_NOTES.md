# Tracsoft AI Lead Qualifier Update Notes

## Current State

- Latest published release: `v1.0.6`
- Latest plugin version in `tracsoft-ai-lead-qualifier.php`: `1.0.6`
- GitHub repo: `https://github.com/tracsoftllc/tracsoft-ai-lead-qualifier`
- Release ZIP asset name expected by the updater: `tracsoft-ai-lead-qualifier.zip`
- Latest update package URL:
  `https://github.com/tracsoftllc/tracsoft-ai-lead-qualifier/releases/download/v1.0.6/tracsoft-ai-lead-qualifier.zip`

## Important Update Packaging Detail

Do not rely on GitHub's auto-generated source archive for WordPress updates.
The installable ZIP should contain this top-level folder:

```text
tracsoft-ai-lead-qualifier/
  tracsoft-ai-lead-qualifier.php
  includes/
  assets/
```

The updater in `includes/class-updater.php` now prefers a GitHub release asset named
`tracsoft-ai-lead-qualifier.zip`. It falls back to the tag archive only if no ZIP
release asset exists.

## Release Process

1. Bump both version values in `tracsoft-ai-lead-qualifier.php`:
   - Plugin header `Version`
   - `TRACSOFT_LB_VERSION`
2. Commit the version bump.
3. Build the ZIP from the committed tree:

```bash
git archive --format=zip --prefix=tracsoft-ai-lead-qualifier/ -o /tmp/tracsoft-ai-lead-qualifier.zip HEAD
```

4. Tag and push:

```bash
git tag vX.Y.Z
git push origin main --tags
```

5. Create a GitHub release for the tag and upload `/tmp/tracsoft-ai-lead-qualifier.zip`
   as `tracsoft-ai-lead-qualifier.zip`.

## Cache Notes For Testing

If a WordPress site is on an older version but no update appears, clear these site
transients and re-check updates:

```bash
wp transient delete --network tracsoft_lb_github_release
wp transient delete --network update_plugins
wp plugin list --update=available
```

In WP Admin, also try **Dashboard > Updates > Check again**.

The plugin caches GitHub release checks for 6 hours, and WordPress separately caches
plugin update results.

## Recent Testing History

- `v1.0.3`: Added `padding: 0;` to `.tlb-bubble`.
- `v1.0.4`: Changed updater to prefer a proper release asset ZIP.
- `v1.0.5`: Test-only version bump to validate update visibility.
- `v1.0.6`: Test-only version bump to validate automatic updates from `1.0.5`.
