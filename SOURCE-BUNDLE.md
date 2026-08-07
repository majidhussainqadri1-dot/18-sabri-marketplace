# Canonical Source Bundle

The complete File 18 source baseline is stored as ordered Base64 chunks under `bundles/` because this repository import was performed through a size-limited GitHub connector.

Run:

```bash
bash reconstruct-source.sh
```

The script concatenates the ordered chunks, decodes and verifies the immutable baseline source archive, applies the audited PHP 8.1 compatibility patch, and reconstructs the canonical `18-marketplace/` source tree.

- Verified baseline source archive SHA-256: `94c71d70f406b8c583b9137b376a1ec887fda0fb5bb4218b4abb0f93245c48e4`
- Audited compatibility patch: `patches/php81-compat.patch`
- Deterministic final installable package SHA-256: `4e5fd6d2ea7fe989b176aabd3a778ce729b622954ea566ab14cf3e1037a5266d`

GitHub Actions reconstructs the source, applies the compatibility correction, runs PHP/JavaScript/CSS/static-contract QA, and builds the final package twice to prove reproducibility.
