# Canonical Source Bundle

The complete File 18 source tree is stored as ordered Base64 chunks under `bundles/` because this repository import was performed through a size-limited GitHub connector.

Run:

```bash
bash reconstruct-source.sh
```

The script concatenates the ordered chunks, decodes the immutable source archive, verifies its SHA-256, and reconstructs the canonical `18-marketplace/` tree.

- Source archive SHA-256: `94c71d70f406b8c583b9137b376a1ec887fda0fb5bb4218b4abb0f93245c48e4`
- Deterministic installable package SHA-256: `b1776d6fd45248dc29d5fde814f41b06f41ac392338bc79f22931b4e9e0901c8`

GitHub Actions reconstructs the source before PHP/JavaScript/CSS/static-contract QA and before running the deterministic package build twice.
