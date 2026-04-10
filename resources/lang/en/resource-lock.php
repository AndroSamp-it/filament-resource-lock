<?php

return [
    'other_user' => 'another user',
    'locked_by' => 'Locked by :user',

    'blocked_resource_notice_modal' => [
        'label' => 'Resource locked',
        'heading' => 'Resource is being edited',
        'description' => 'This resource is being edited by :user. Please try again later.',
        'ask_to_unblock' => 'Request to unlock',
        'back' => 'Back to list',
        'save_and_unlock' => 'Save and release',
    ],

    'waiting_for_resource_unlock_modal' => [
        'heading' => 'Releasing resource',
        'description' => 'This will take a few seconds. Please wait. The page will reload automatically.',
    ],

    'waiting_for_resource_unlock_notify_modal' => [
        'heading' => 'Notification sent',
        'description' => 'Waiting for the current editor to respond.',
    ],

    'evicted_after_save_unlock' => [
        'title' => 'Lock transferred to another user',
        'body' => 'Another user saved changes and took over editing. You no longer hold the lock. New editor: :user.',
    ],

    'notifications' => [
        'ask_to_unblock' => [
            'title' => 'Unlock request',
            'body' => ':user is asking you to release this resource.',
            'accept' => 'Save and release',
            'decline' => 'Decline',
        ],
        'unlock_declined' => [
            'title' => 'Unlock request declined',
            'body' => ':user declined your unlock request.',
        ],
        'unlock_accepted' => [
            'title' => 'Unlock request accepted',
            'body' => ':user released the resource.',
        ],
    ],
];
