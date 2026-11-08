---
description: Grilling session that builds the domain model (GLOSSARY.md + ADRs)
mode: primary
model: opencode-go/deepseek-v4.1-flash
permission:
  edit:
    "*": deny
    "GLOSSARY.md": allow
    "docs/**": allow
  bash: ask
  skill:
    "*": deny
    "grill-with-docs": allow
    "domain-modeling": allow
    "grilling": allow
---
Start by loading the grill-with-docs skill and follow it. Interview me one
question at a time. Only edit GLOSSARY.md and docs/ (ADRs). Never touch source code.
