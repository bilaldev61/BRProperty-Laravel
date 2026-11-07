<?php

namespace App\Enums;

/**
 * The roles an admin account can hold, and what each one may do.
 *
 * Abilities are plain strings because three places have to agree on them: route
 * middleware (`ability:properties.manage`), Blade (`@can('settings.manage')`)
 * and the sidebar filter. Each role states its list outright rather than
 * inheriting from a parent - four short lists are quicker to audit than a
 * hierarchy you cannot see.
 */
enum Role: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Agent = 'agent';
    case Editor = 'editor';

    /**
     * Every ability that exists, keyed by ability and described in words a
     * person would use. The team screen renders this as the permission matrix,
     * so a new ability shows up there without further edits.
     *
     * @var array<string, string>
     */
    public const ABILITIES = [
        'dashboard.view' => 'See the dashboard',
        'finance.view' => 'See portfolio value, sold prices and call spend',
        'properties.view' => 'See listings, including drafts',
        'properties.manage' => 'Add, edit and delete listings',
        'enquiries.view' => 'Read enquiries',
        'enquiries.manage' => 'Reply to enquiries and move them along',
        'enquiries.delete' => 'Delete enquiries',
        'calls.view' => 'See calls taken by the phone agent',
        'appointments.view' => 'See appointments',
        'appointments.manage' => 'Confirm, move and cancel appointments',
        'assistant.view' => 'Read assistant conversations',
        'testimonials.manage' => 'Publish and edit reviews',
        'settings.manage' => 'Change site settings and branding',
        'team.manage' => 'Add staff and change their roles',
    ];

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Manager => 'Manager',
            self::Agent => 'Agent',
            self::Editor => 'Editor',
        };
    }

    /** One line explaining the role, shown wherever it can be assigned. */
    public function summary(): string
    {
        return match ($this) {
            self::Owner => 'Full access, including site settings and who else can sign in.',
            self::Manager => 'Runs the day to day: listings, enquiries, calls, appointments, reviews and the sales figures.',
            self::Agent => 'Answers enquiries and handles appointments. Can read listings but not change them, and never sees what the portfolio is worth.',
            self::Editor => 'Keeps listings and reviews up to date. Nothing else, and no sales figures.',
        };
    }

    /**
     * The abilities this role holds.
     *
     * `finance.view` is the line that keeps money off the shop floor: an agent
     * answers enquiries and an editor maintains listings, and neither has any
     * need for what the portfolio is worth or what sold for how much. Both get
     * operational figures instead.
     *
     * @return list<string>
     */
    public function abilities(): array
    {
        $all = array_keys(self::ABILITIES);

        return match ($this) {
            self::Owner => $all,
            self::Manager => array_values(array_diff($all, ['settings.manage', 'team.manage'])),
            self::Agent => [
                'dashboard.view',
                'properties.view',
                'enquiries.view',
                'enquiries.manage',
                'calls.view',
                'appointments.view',
                'appointments.manage',
                'assistant.view',
            ],
            self::Editor => [
                'dashboard.view',
                'properties.view',
                'properties.manage',
                'testimonials.manage',
            ],
        };
    }

    public function can(string $ability): bool
    {
        return in_array($ability, $this->abilities(), true);
    }

    /**
     * @return array<string, string> stored value => label, for selects
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $role) {
            $options[$role->value] = $role->label();
        }

        return $options;
    }

    /** The chip tone for a role, kept here so it looks the same everywhere. */
    public function tone(): string
    {
        return match ($this) {
            self::Owner => 'brass',
            self::Manager => 'indigo',
            self::Agent => 'teal',
            self::Editor => 'clay',
        };
    }
}
