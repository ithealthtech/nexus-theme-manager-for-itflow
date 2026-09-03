# Nexus documentation

End-user documentation is published at
<https://ithealthtech.github.io/nexus-theme-manager-for-itflow/>.

## Guides

| Document | What it covers |
| --- | --- |
| [Theme Manager guide](theme-manager-guide.md) | Beginner walkthrough of Theme Studio, drafts, previews, and publishing |
| [Architecture](architecture.md) | Component layout and privilege boundaries |
| [Design spec](design-spec.md) | Visual system and surface definitions |
| [Managed file list](changed-files.md) | Exactly which ITFlow templates Nexus manages |

## Validation

| Document | What it covers |
| --- | --- |
| [Test report](test-report.md) | Validation results |
| [Lifecycle test report](lifecycle-test-report.md) | Install, enable, pause, restore, and uninstall coverage |

## Release notes

Newest first. Each release is pinned to a specific ITFlow revision — read the notes for
your target version before installing.

| Version | Notes |
| --- | --- |
| 3.9.1 | [release-v3.9.1.md](release-v3.9.1.md) |
| 3.9.0 | [release-v3.9.0.md](release-v3.9.0.md) |
| 3.8.0 | [release-v3.8.0.md](release-v3.8.0.md) |
| 3.7.0 | [release-v3.7.0.md](release-v3.7.0.md) |
| 3.6.0 | [release-v3.6.0.md](release-v3.6.0.md) |
| 3.5.0 | [release-v3.5.0.md](release-v3.5.0.md) |
| 3.4.1 | [release-v3.4.1.md](release-v3.4.1.md) |
| 3.4.0 | [release-v3.4.0.md](release-v3.4.0.md) |
| 3.3.0 | [release-v3.3.0.md](release-v3.3.0.md) |
| 3.2.0 | [release-v3.2.0.md](release-v3.2.0.md) |
| 3.1.1 | [release-v3.1.1.md](release-v3.1.1.md) |
| 3.1.0 | [release-v3.1.0.md](release-v3.1.0.md) |
| 3.0.2 | [release-v3.0.2.md](release-v3.0.2.md) |
| 3.0.1 | [release-v3.0.1.md](release-v3.0.1.md) |
| 3.0.0 | [release-v3.0.0.md](release-v3.0.0.md) |
| 2.6.0 | [release-v2.6.0.md](release-v2.6.0.md) |
| 2.5.4 | [release-v2.5.4.md](release-v2.5.4.md) |
| 2.5.3 | [release-v2.5.3.md](release-v2.5.3.md) |
| 2.5.2 | [release-v2.5.2.md](release-v2.5.2.md) |
| 2.5.1 | [release-v2.5.1.md](release-v2.5.1.md) |
| 2.5.0 | [release-v2.5.0.md](release-v2.5.0.md) |
| 2.4.0 | [release-v2.4.0.md](release-v2.4.0.md) |
| 2.3.0 | [release-v2.3.0.md](release-v2.3.0.md) |
| 2.2.0 | [release-v2.2.0.md](release-v2.2.0.md) |
| 2.1.0 | [release-v2.1.0.md](release-v2.1.0.md) |
| 2.0.0 | [release-v2.0.0.md](release-v2.0.0.md) |

The consolidated history is in [CHANGELOG.md](../CHANGELOG.md).

## Project documents

[README](../README.md) · [CONTRIBUTING](../CONTRIBUTING.md) ·
[SECURITY](../SECURITY.md) · [LICENSE](../LICENSE) · [NOTICE](../NOTICE.md)

## The rule that matters most

Disable or uninstall Nexus **before** updating ITFlow. Install a Nexus release only when
its compatibility table explicitly supports the new ITFlow revision.

ITFlow has no native theme or plugin hook system, so Nexus manages a bounded set of
verified templates and refuses to run against templates it has not tested. That refusal is
the safety feature — never force past it.
