<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Database\Seeder;

class TicketSeeder extends Seeder
{
    public function run(): void
    {
        $clients = User::where('role', User::ROLE_CLIENT)->get();
        $supportUsers = User::whereIn('role', [
            User::ROLE_SHADOW,
            User::ROLE_ADMIN,
            User::ROLE_SUPER_USER,
            User::ROLE_TECHNICAL,
        ])
            ->where('is_active', true)
            ->get();
        $categories = Category::all();

        if ($clients->isEmpty() || $supportUsers->isEmpty()) {
            $this->command?->warn('Skipping TicketSeeder: missing client or support users.');

            return;
        }

        $seedLocations = [
            ['province' => 'Rizal', 'municipality' => 'Antipolo'],
            ['province' => 'Laguna', 'municipality' => 'Calamba'],
            ['province' => 'Cavite', 'municipality' => 'Dasmarinas'],
            ['province' => 'Cebu', 'municipality' => 'Cebu City'],
            ['province' => 'Iloilo', 'municipality' => 'Iloilo City'],
        ];

        $sampleTickets = [
            [
                'subject' => 'Computer won\'t start up',
                'description' => 'My desktop computer is not turning on at all. I tried different power outlets but nothing happens when I press the power button.',
                'priority' => 'high',
                'status' => 'open',
                'category' => 'Hardware',
            ],
            [
                'subject' => 'Email not receiving messages',
                'description' => 'I\'m not receiving any emails since yesterday morning. I can send emails but nothing is coming in.',
                'priority' => 'medium',
                'status' => 'in_progress',
                'category' => 'Other',
            ],
            [
                'subject' => 'Password reset request',
                'description' => 'I need to reset my password for the company portal. I forgot my current password and can\'t log in.',
                'priority' => 'low',
                'status' => 'resolved',
                'category' => 'Account & Access',
            ],
            [
                'subject' => 'WiFi connection keeps dropping',
                'description' => 'My laptop keeps losing WiFi connection every few minutes. This has been happening for the past week.',
                'priority' => 'medium',
                'status' => 'pending',
                'category' => 'Network & Connectivity',
            ],
            [
                'subject' => 'Software installation help needed',
                'description' => 'I need help installing the new CRM software on my computer. I downloaded it but I\'m not sure about the setup process.',
                'priority' => 'low',
                'status' => 'open',
                'category' => 'Software',
            ],
            [
                'subject' => 'Printer not working after update',
                'description' => 'The office printer stopped working after the latest Windows update. It shows as offline even though it\'s connected.',
                'priority' => 'medium',
                'status' => 'in_progress',
                'category' => 'Other',
            ],
            [
                'subject' => 'Security software alert',
                'description' => 'I received a security alert saying there might be malware on my computer. I ran a scan but want IT to double-check.',
                'priority' => 'urgent',
                'status' => 'open',
                'category' => 'Other',
            ],
            [
                'subject' => 'Mobile app training request',
                'description' => 'I would like training on how to use the company mobile app effectively. I\'m having trouble navigating some features.',
                'priority' => 'low',
                'status' => 'closed',
                'category' => 'Other',
            ],
        ];

        foreach ($sampleTickets as $ticketData) {
            $category = $categories->where('name', $ticketData['category'])->first();
            if (! $category) {
                $category = Category::firstOrCreate(
                    ['name' => $ticketData['category']],
                    [
                        'description' => 'General support category',
                        'color' => '#6B7280',
                        'is_active' => true,
                    ]
                );
                $categories->push($category);
            }

            $client = $clients->random();
            $technician = $supportUsers->random();
            $location = $seedLocations[array_rand($seedLocations)];

            $ticket = Ticket::create([
                'name' => $client->name,
                'contact_number' => $client->phone ?: 'N/A',
                'email' => $client->email,
                'province' => $location['province'],
                'municipality' => $location['municipality'],
                'subject' => $ticketData['subject'],
                'description' => $ticketData['description'],
                'priority' => $ticketData['priority'],
                'status' => $ticketData['status'],
                'user_id' => $client->id,
                'assigned_to' => in_array($ticketData['status'], ['in_progress', 'pending', 'resolved', 'closed']) ? $technician->id : null,
                'category_id' => $category->id,
                'created_at' => now()->subDays(rand(0, 30)),
                'resolved_at' => in_array($ticketData['status'], ['resolved', 'closed']) ? now()->subDays(rand(0, 5)) : null,
                'closed_at' => $ticketData['status'] === 'closed' ? now()->subDays(rand(0, 3)) : null,
            ]);

            // Add some replies for tickets that are not just open
            if (in_array($ticketData['status'], ['in_progress', 'pending', 'resolved', 'closed'])) {
                // Agent first reply
                TicketReply::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $technician->id,
                    'message' => 'Thank you for reporting this issue. I\'ve been assigned to help you and I\'m looking into it now.',
                    'is_internal' => false,
                    'created_at' => $ticket->created_at->addHours(2),
                ]);

                // Client follow-up (sometimes)
                if (rand(0, 1)) {
                    TicketReply::create([
                        'ticket_id' => $ticket->id,
                        'user_id' => $client->id,
                        'message' => 'Thank you for the quick response. Please let me know if you need any additional information.',
                        'is_internal' => false,
                        'created_at' => $ticket->created_at->addHours(4),
                    ]);
                }

                // Resolution reply for resolved/closed tickets
                if (in_array($ticketData['status'], ['resolved', 'closed'])) {
                    TicketReply::create([
                        'ticket_id' => $ticket->id,
                        'user_id' => $technician->id,
                        'message' => 'I\'ve resolved the issue. Please let me know if you experience any further problems.',
                        'is_internal' => false,
                        'created_at' => $ticket->resolved_at ?? $ticket->closed_at,
                    ]);
                }
            }

            // Add satisfaction rating for closed tickets
            if ($ticketData['status'] === 'closed' && rand(0, 1)) {
                $ticket->update([
                    'satisfaction_rating' => rand(3, 5),
                    'satisfaction_comment' => rand(0, 1) ? 'Great service, issue was resolved quickly!' : null,
                ]);
            }
        }
    }
}
