# Field office is stored on the ticket, not looked up from the requester

Each ticket keeps the department it came from (its field office) as its own value, set when the ticket is created. Looking it up through the requester would move old tickets between offices whenever a client changes department or is deleted, and imported tickets were all filed under one placeholder account. So reports, filters and exports read the stored value; renaming a department renames it on its tickets, and an admin or super user can correct one ticket by hand (recorded in the audit trail).
