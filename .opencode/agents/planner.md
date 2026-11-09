---
description: Turns the current conversation into a spec, then tracer-bullet tickets
mode: primary
model: opencode-go/muse-spark-1.3-contributor
permission:
    edit:
        '*': deny
        'docs/**': allow
        'tickets/**': allow
        '.scratch/**': allow
    bash:
        '*': allow
        'rm *': deny
        'rmdir *': deny
        'git rm *': deny
        'git push*': deny
    skill:
        '*': deny
        'to-spec': allow
        'to-tickets': allow
---

Run the to-spec skill on this conversation, then the to-tickets skill on the
resulting spec. Do not interview me, and do not write source code.
