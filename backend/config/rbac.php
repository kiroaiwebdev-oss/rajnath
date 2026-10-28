<?php

/*
| Role and permission catalogue. Authorization is default-deny: a staff member can only do what
| a permission below explicitly grants. "owner" is granted everything via Gate::before, but every
| owner action is still audited. Roles are synced by `php artisan arj:rbac-sync` (idempotent).
*/

return [
    'permissions' => [
        // Team, audit, platform
        'team.view', 'team.manage', 'roles.manage',
        'audit.view',
        'settings.view', 'settings.manage', 'providers.manage',
        'health.view',
        // Content
        'content.author', 'content.review', 'content.publish', 'media.manage',
        // Catalogue
        'catalogue.view', 'catalogue.manage', 'prices.manage',
        // Customers & bookings
        'customers.view', 'customers.restrict',
        'bookings.view', 'bookings.manage',
        // Practitioners
        'practitioners.view', 'practitioners.review', 'practitioners.manage',
        // Reports and fulfilment
        'reports.fulfil', 'reports.review',
        'puja.fulfil', 'suppliers.manage',
        // Finance
        'finance.view', 'refunds.request', 'refunds.approve',
        'payouts.approve', 'commission.propose', 'commission.approve',
        'reconciliation.manage',
        // Support & privacy
        'support.view', 'support.handle', 'privacy.handle', 'privacy.approve',
        // AI
        'assistant.knowledge',
    ],

    'roles' => [
        'owner' => ['*'],
        'administrator' => [
            'team.view', 'team.manage', 'audit.view', 'settings.view', 'settings.manage', 'health.view',
            'content.author', 'content.review', 'content.publish', 'media.manage',
            'catalogue.view', 'catalogue.manage', 'customers.view', 'customers.restrict',
            'bookings.view', 'bookings.manage', 'practitioners.view', 'practitioners.review',
            'practitioners.manage', 'reports.review', 'suppliers.manage', 'support.view',
            'support.handle', 'privacy.handle', 'assistant.knowledge', 'finance.view',
            'commission.propose', 'refunds.request',
        ],
        'finance' => [
            'finance.view', 'refunds.request', 'refunds.approve', 'payouts.approve',
            'commission.propose', 'reconciliation.manage', 'customers.view', 'bookings.view',
        ],
        'support' => [
            'support.view', 'support.handle', 'customers.view', 'bookings.view', 'refunds.request',
            'privacy.handle',
        ],
        'content_author' => ['content.author', 'media.manage', 'catalogue.view'],
        'content_reviewer' => ['content.author', 'content.review', 'media.manage', 'catalogue.view'],
        'content_publisher' => ['content.author', 'content.review', 'content.publish', 'media.manage', 'catalogue.view'],
        'fulfilment' => ['reports.fulfil', 'puja.fulfil', 'bookings.view', 'catalogue.view'],
        'practitioner' => [], // practitioner abilities are scoped to their own records by policies
    ],

    // Permissions that always require a fresh step-up (password + TOTP within the window).
    'step_up' => [
        'team.manage', 'roles.manage', 'settings.manage', 'providers.manage', 'prices.manage',
        'refunds.approve', 'payouts.approve', 'commission.approve', 'privacy.approve',
    ],

    'step_up_minutes' => (int) env('STEP_UP_MINUTES', 10),
];
