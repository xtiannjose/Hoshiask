<?php

namespace Database\Seeders;

use App\Models\CannedResponse;
use App\Models\KnowledgeArticle;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Ready-to-use starter entries for Canned Responses (staff reply templates) and the Knowledge Base
 * (articles clients can read). Everything described here matches how the system really behaves.
 *
 * Safe to run more than once, and on the live server: an entry is created only when no entry with the same
 * title exists, so nothing that staff have edited or deleted-and-rewritten is ever overwritten.
 *
 *     php artisan db:seed --class=StarterContentSeeder
 */
class StarterContentSeeder extends Seeder
{
    public function run(): void
    {
        // The author is shown on each entry and the entries are removed if that account is ever deleted,
        // so use a regular admin account rather than a hidden one.
        $author = $this->author();
        if ($author === null) {
            $this->command?->warn('No active admin or super user found, so no starter content was added. Create one first.');

            return;
        }

        $cannedAdded = 0;
        foreach ($this->cannedResponses() as $title => $body) {
            $response = CannedResponse::firstOrCreate(
                ['title' => $title],
                ['body' => $body, 'created_by_user_id' => $author->id],
            );
            $cannedAdded += $response->wasRecentlyCreated ? 1 : 0;
        }

        $articlesAdded = 0;
        foreach ($this->knowledgeArticles() as $article) {
            if (KnowledgeArticle::where('title', $article['title'])->exists()) {
                continue;
            }

            KnowledgeArticle::create([
                'created_by_user_id' => $author->id,
                'category' => $article['category'],
                'title' => $article['title'],
                'slug' => KnowledgeArticle::generateAvailableSlug($article['title']),
                'body' => $article['body'],
                'is_published' => true,
                'published_at' => now(),
            ]);
            $articlesAdded++;
        }

        $this->command?->info("Starter content: {$cannedAdded} canned response(s) and {$articlesAdded} knowledge base article(s) added.");
    }

    private function author(): ?User
    {
        foreach ([User::ROLE_ADMIN, User::ROLE_SUPER_USER] as $role) {
            $user = User::where('role', $role)->where('is_active', true)->orderBy('id')->first();
            if ($user !== null) {
                return $user;
            }
        }

        return null;
    }

    /**
     * @return array<string, string> title => body
     */
    public function cannedResponses(): array
    {
        return [
            'Ticket received' => <<<'TXT'
Hello,

Thank you for contacting our support team. We have received your ticket and a member of the team is now reviewing it.

We will update you here as soon as work begins. If you have anything to add in the meantime, such as a screenshot or the exact error message, you can reply to this message.

Thank you for your patience.
TXT,

            'Request for more information' => <<<'TXT'
Hello,

To look into this properly, could you please send us the following?

1. A screenshot of the problem, or the exact error message you see.
2. When it started, and whether anything changed just before it (for example an update, a move or a new device).
3. Whether it affects only you or other people in your office too.

You can reply here and attach files directly to this ticket. We will continue as soon as we receive your reply.

Thank you.
TXT,

            'Work in progress update' => <<<'TXT'
Hello,

A quick update: we are working on your request and are checking the cause of the issue. We will let you know as soon as we have a result, or if we need anything else from you.

Thank you for your patience.
TXT,

            'Follow-up: waiting for your reply' => <<<'TXT'
Hello,

We are following up on this ticket. We are still waiting for your reply to our last message before we can continue.

Please reply here with the details we asked for when you can. If the issue is already fixed, or you no longer need help with it, please let us know. You can also cancel the request yourself from the ticket page.

Thank you.
TXT,

            'Onsite visit: schedule' => <<<'TXT'
Hello,

This issue needs a technician to visit your office. To arrange it, please reply with:

1. A day and time that works for you.
2. The name and contact number of the person who will meet our technician.
3. The exact room or area where the equipment is located.

We will confirm the schedule here once everything is set.

Thank you.
TXT,

            'Password reset completed' => <<<'TXT'
Hello,

Your account password has been reset by our team. Please sign in with the new password you were given.

For your security, keep it private and do not share it with anyone. We will never ask for your password, and it should never be written in a ticket. If you think someone else knows it, tell us right away and we will change it again.

If you still cannot sign in, reply here and we will check your account.
TXT,

            'Issue resolved: please confirm' => <<<'TXT'
Hello,

We have completed the work on this request and set the ticket to Resolved. Please test it and let us know if everything is working as expected.

If it is fixed, please open the ticket and leave a rating from 1 to 5 stars; your feedback helps us improve. If something is still wrong, reply here or choose "Reopen Ticket" and tell us what you see, and we will pick it up again.

If we do not hear from you, the ticket will close automatically after 7 days.

Thank you.
TXT,
        ];
    }

    /**
     * @return list<array{category: string, title: string, body: string}>
     */
    public function knowledgeArticles(): array
    {
        return [
            [
                'category' => 'guide',
                'title' => 'How to submit a support ticket',
                'body' => <<<'TXT'
Use a ticket whenever you need help from the support team. Every request gets its own ticket number, so you and the team can follow it from start to finish.

1. Choose "New Ticket" in the sidebar or at the top of the page.
2. Check your name, contact number and email, so the team can reach you.
3. Choose your province and your municipality or city.
4. Write a short, specific subject. For example: "Printer in Records Office shows offline".
5. Choose the category that fits best.
6. Describe the problem in the description box (see "How to write a ticket that gets solved faster").
7. Attach a screenshot or a document if it helps. Attachments are optional.
8. Tick the consent box and submit.

After you submit
- Your ticket gets a ticket number and starts as Open.
- The team reviews it and assigns it to a technician.
- You can follow its progress and exchange messages on the ticket page. Find it under "My Tickets" in the sidebar.
TXT,
            ],
            [
                'category' => 'guide',
                'title' => 'How to write a ticket that gets solved faster',
                'body' => <<<'TXT'
The clearer the ticket, the fewer questions we need to ask and the sooner a technician can start fixing the problem. Before you submit, try to answer these:

What is wrong?
Describe what you see or what is not working. If there is an error message, copy it exactly.

What did you expect to happen?
For example: "I expected the report to print, but nothing comes out."

When did it start?
Tell us whether it began today, after an update, after a move, or after something else changed.

Who is affected?
Say whether it only affects you or other people in your office too, and which device or program is involved.

What have you already tried?
For example restarting the computer or reconnecting a cable. This saves the technician from repeating the same steps.

Add a screenshot
A picture of the whole screen, including the error, often says more than a paragraph. You can attach files when you create the ticket and again whenever you reply.

Good habits
- One problem per ticket. If you have two unrelated issues, send two tickets so each one reaches the right person.
- Use a specific subject. "Cannot print" is hard to act on; "Printer in Records Office shows offline" is easy.
- Give a contact number you can answer during working hours.
- Never type your password or other secrets in a ticket.
TXT,
            ],
            [
                'category' => 'faq',
                'title' => 'What do the ticket statuses mean?',
                'body' => <<<'TXT'
Every ticket shows a status, so you always know where your request stands.

Open
We have received your ticket, but work has not really started yet.

In Progress
A technician is working on it.

Pending
We are waiting for something before we can continue, such as your reply, a schedule, an approval or replacement equipment.

Resolved
We believe the problem is fixed. Please check and let us know. You can still reply, rate the service or reopen the ticket.

Closed
The ticket is finished and you can no longer reply to it. See "How resolved tickets are confirmed and closed".

Cancelled
The request was called off before work began, and nothing more will be done on it.

If a ticket is Pending, look for our latest message and reply to it. Your reply is usually all it takes to get the ticket moving again.
TXT,
            ],
            [
                'category' => 'faq',
                'title' => 'Which files can I attach to a ticket, and how big can they be?',
                'body' => <<<'TXT'
You can attach files when you create a ticket and whenever you reply to it. Attachments are optional.

Accepted file types
- Pictures: JPG, JPEG, PNG
- Documents: PDF, DOCX (Word), XLSX (Excel), TXT (plain text). Save an older .doc or .xls file as .docx or .xlsx first.

File size
Each file can be up to 10 MB. The upload box tells you the exact limit and warns you before you send a file that is too large. If a file is too big, you can:
- Send a screenshot instead of a full-size photo.
- Save the document as a PDF, or remove large pictures from it.
- Split it into several smaller files.

Tip: to capture a problem on your screen, press the Print Screen key (or the Windows key + Shift + S), then paste the picture into your reply or save it and attach it.
TXT,
            ],
            [
                'category' => 'faq',
                'title' => 'Can I cancel a ticket I no longer need?',
                'body' => <<<'TXT'
Yes, as long as nobody has started working on it.

You can cancel a ticket while its status is Open or Pending. Once a technician is working on it (In Progress), or it is Resolved or Closed, it can no longer be cancelled. If work has already started and you no longer need it, reply on the ticket to tell us.

To cancel a ticket
1. Open the ticket from "My Tickets" in the sidebar.
2. Choose "Cancel Ticket" in the Actions box.
3. Tell us briefly why. A reason is required.

What happens next
- The ticket moves to Cancelled and stays in your ticket history together with the reason you gave.
- It can no longer be replied to.
- If you cancelled by mistake, contact the support team. Staff can reopen a cancelled ticket.
TXT,
            ],
            [
                'category' => 'policy',
                'title' => 'How resolved tickets are confirmed and closed',
                'body' => <<<'TXT'
When our team finishes the work, the ticket is set to Resolved. This is your chance to check that everything works. This is what happens next.

If the problem is fixed
Open the ticket and give us a rating from 1 to 5 stars. A comment is optional, unless you give 1 or 2 stars: then please tell us what went wrong so we can put it right.

If the problem is not fixed
Reply on the ticket, or choose "Reopen Ticket" and explain what is still wrong. The team will pick it up again.

If we do not hear from you
A resolved ticket that nobody touches is closed automatically after 7 days. We send you a reminder email 2 days before that happens, so you have time to reply or reopen it.

After a ticket is closed
- You can no longer reply to a closed ticket.
- You can still reopen it with "Reopen Ticket" on the ticket page, within 7 days of the day it was closed.
- After those 7 days, please submit a new ticket and mention the old ticket number, so we can find the history quickly.
TXT,
            ],
            [
                'category' => 'guide',
                'title' => 'Keeping your account secure',
                'body' => <<<'TXT'
Your account gives access to your tickets and contact details, so please look after it.

Good habits
- Never share your password, not even with the support team. We will never ask for it, and it should never be written in a ticket.
- Do not write it down where other people can see it, and do not reuse it for other accounts.
- Sign out when you use a shared computer. "Sign out" is in the menu with your name at the top right.
- If you think someone else knows your password, tell your administrator or the support team right away so it can be changed.

Changing or resetting your password
- Client accounts: the password is managed by your administrator or the support team. There is no automatic reset, and you cannot change it yourself from the app. If you forgot it, or you would like it changed, contact your administrator and they will set a new one for you.
- Staff accounts: change your own password under "Account Settings" in the sidebar (at least 8 characters, and not a common password). If you forgot it, ask an administrator to set a new one.
TXT,
            ],
        ];
    }
}
