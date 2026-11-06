# Household Budgeting

The shared vocabulary for the household budgeting app: households, their members, and the budgets and periods they share.

## Language

**Household**:
The shared scope that owns budgets, budget periods, and transactions.
_Avoid_: Account, group, team

**Member**:
A user who belongs to a household.
_Avoid_: Participant, collaborator

**Membership**:
A user's participation in a household, carrying their role and notification preferences.
_Avoid_: Pivot, household-user

**Budget**:
A spending category within a household, with an amount allocated for each period.
_Avoid_: Category, envelope

**Period**:
A budgeting window that starts on the household's payday and is named for the month it starts in.
_Avoid_: Month, cycle

**Transaction**:
A single amount against a budget in a period, of type expense, income, transfer out, or transfer in.
_Avoid_: Entry, record

**Transfer**:
A pair of linked transactions — a transfer out and a transfer in — moving money between budgets without changing the household total.
_Avoid_: Move (for the record)

**Move**:
The action of creating a transfer.
_Avoid_: Transfer (for the action)
