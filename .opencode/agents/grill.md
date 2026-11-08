---
description: Grilling session that builds the domain model (GLOSSARY.md + ADRs)
mode: primary
model: opencode-go/muse-spark-1.3-contributor
permission:
    edit:
        '*': deny
        'GLOSSARY.md': allow
        'docs/**': allow
    bash:
        '*': allow
        'rm *': deny
        'rmdir *': deny
        'git rm *': deny
        'git push*': deny
    skill:
        '*': deny
        'grill-with-docs': allow
        'domain-modeling': allow
        'grilling': allow
---

Start by loading the grill-with-docs skill and follow it. Interview me one
question at a time. Only edit GLOSSARY.md and docs/ (ADRs). Never touch source code.
