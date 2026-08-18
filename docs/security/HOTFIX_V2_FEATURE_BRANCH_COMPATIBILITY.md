# Hotfix V2 feature-branch compatibility

## Baselines

```text
hotfix base=d5bd587ec6ed2305fc4a6d4aaa9f3a9cc548d9b1
feature target=9e92fe9e75d37d8531299d2290cb149ddd43a18a
feature branch=feature/ai-sales-agents
```

The compatibility check used a disposable detached worktree at the exact
feature target. The real feature worktree, branch ref and index were not
modified.

## Proof performed before commit

The complete staged Hotfix V2 patch, including additions and deletion, was
checked and applied in the disposable worktree using Git's three-way mode:

```text
git apply --3way --check: exit 0
git apply --3way: exit 0
conflicts: 0
rejected hunks: 0
```

PHP syntax checks then passed for every changed application, bootstrap, route
and test PHP file in the feature worktree. The patch does not depend on Stage
13/13B domain, UI or migration classes.

## Porting boundary

Port only the final commit whose subject is:

```text
security(mail): make Unisender pre-auth ingress stateless
```

Do not merge the hotfix branch wholesale and do not amend/rebase feature
history. Before an eventual port, repeat a no-commit cherry-pick in a disposable
worktree at the then-current reviewed feature HEAD and rerun the webhook,
commercial-mail and full regression suites.

This document is compatibility evidence only. No feature-branch commit, push or
production deployment is authorized by it.
