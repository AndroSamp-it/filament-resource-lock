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

    'audit' => [
        'action_label'        => 'Change history',
        'modal_heading'       => 'Change history',
        'close'               => 'Close',
        'table_heading'       => 'Saved versions',
        'empty_state_heading' => 'No changes recorded yet',
        'empty_state_description' => 'Changes will appear here after each save while a lock is held.',

        'columns' => [
            'version'    => 'Version',
            'date'       => 'Date',
            'author'     => 'Author',
            'changes'    => 'Changes',
            'lock_cycle' => 'Lock session',
        ],

        'actions' => [
            'view_diff' => 'View diff',
            'rollback_changes' => 'Rollback changes',
        ],

        'rollback' => [
            'modal_heading' => 'Rollback changes · v:version',
            'modal_description' => 'Select fields to restore to their "WAS" value from this version.',
            'fields_label' => 'Fields to rollback',
            'submit' => 'Rollback selected',
            'success' => 'Rollback completed for :count fields.',
            'errors' => [
                'empty_selection' => 'Select at least one field to rollback.',
                'failed' => 'Failed to rollback the selected changes.',
            ],
        ],

        'diff' => [
            'modal_heading'   => 'What changed · v:version',
            'heading'         => 'What changed',
            'was'             => 'WAS',
            'became'          => 'BECAME',
            'yes'             => 'Yes',
            'no'              => 'No',
            'changes_count'   => 'changes',
            'snapshot_date'   => 'Snapshot from :date · :author',
            'snapshot_meta'   => 'Snapshot from :date · :author',
        ],
    ],
];
