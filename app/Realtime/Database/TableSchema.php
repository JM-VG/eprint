<?php

namespace App\Realtime\Database;

/**
 * What the relational schema used to enforce, kept for the Realtime Database:
 * column types (so stored values keep their PHP types), column defaults,
 * unique columns and what happens to child records when a parent is deleted.
 *
 * Tables that are not listed here (sessions, cache, ...) are stored as-is.
 */
class TableSchema
{
    public const CASCADE = 'cascade';

    public const SET_NULL = 'set null';

    public const RESTRICT = 'restrict';

    private const TIMESTAMPS = ['created_at' => 'datetime', 'updated_at' => 'datetime'];

    /**
     * @var array<string, array{columns: array<string, string>, defaults?: array<string, mixed>, unique?: list<string>, foreign?: array<string, array{0: string, 1: string}>}>
     */
    private const TABLES = [
        'users' => [
            'columns' => [
                'id' => 'int', 'student_id' => 'string', 'username' => 'string', 'name' => 'string',
                'first_name' => 'string', 'last_name' => 'string', 'email' => 'string',
                'email_verified_at' => 'datetime', 'password' => 'string', 'role' => 'string',
                'status' => 'string', 'must_change_password' => 'bool', 'approved_by' => 'int',
                'approved_at' => 'datetime', 'rejection_reason' => 'string', 'remember_token' => 'string',
            ] + self::TIMESTAMPS,
            'defaults' => ['role' => 'student', 'status' => 'pending', 'must_change_password' => false],
            'unique' => ['student_id', 'username', 'email'],
            'foreign' => ['approved_by' => ['users', self::SET_NULL]],
        ],
        'password_reset_tokens' => [
            'columns' => ['id' => 'int', 'email' => 'string', 'token' => 'string', 'created_at' => 'datetime'],
            'unique' => ['email'],
        ],
        'locations' => [
            'columns' => ['id' => 'int', 'name' => 'string', 'slug' => 'string'] + self::TIMESTAMPS,
            'unique' => ['slug'],
        ],
        'station_sessions' => [
            'columns' => [
                'id' => 'int', 'user_id' => 'int', 'location_id' => 'int', 'opened_at' => 'datetime',
                'last_seen_at' => 'datetime', 'closed_at' => 'datetime', 'close_reason' => 'string',
            ] + self::TIMESTAMPS,
            'foreign' => ['user_id' => ['users', self::CASCADE], 'location_id' => ['locations', self::RESTRICT]],
        ],
        'services' => [
            'columns' => [
                'id' => 'int', 'type' => 'string', 'name' => 'string', 'paper_size' => 'string',
                'color_mode' => 'string', 'unit' => 'string', 'pieces_per_set' => 'int',
                'price' => 'decimal', 'is_active' => 'bool',
            ] + self::TIMESTAMPS,
            'defaults' => ['price' => '0.00', 'is_active' => true],
        ],
        'commission_payouts' => [
            'columns' => [
                'id' => 'int', 'user_id' => 'int', 'paid_by' => 'int', 'period_start' => 'date',
                'period_end' => 'date', 'amount' => 'decimal', 'notes' => 'string', 'paid_at' => 'datetime',
            ] + self::TIMESTAMPS,
            'foreign' => ['user_id' => ['users', self::RESTRICT], 'paid_by' => ['users', self::SET_NULL]],
        ],
        'print_jobs' => [
            'columns' => [
                'id' => 'int', 'claim_code' => 'string', 'user_id' => 'int', 'location_id' => 'int',
                'service_id' => 'int', 'source' => 'string', 'original_name' => 'string',
                'stored_path' => 'string', 'pdf_path' => 'string', 'file_type' => 'string',
                'file_size' => 'int', 'page_count' => 'int', 'page_range' => 'string', 'copies' => 'int',
                'duplex' => 'bool', 'sets' => 'int', 'notes' => 'string', 'unit_price' => 'decimal',
                'estimated_total' => 'decimal', 'final_total' => 'decimal', 'sheets_used' => 'int',
                'status' => 'string', 'payment_method' => 'string', 'payment_status' => 'string',
                'reject_reason' => 'string', 'received_by' => 'int', 'claimed_at' => 'datetime',
                'files_deleted_at' => 'datetime',
            ] + self::TIMESTAMPS,
            'defaults' => [
                'source' => 'online', 'copies' => 1, 'duplex' => false,
                'status' => 'pending', 'payment_status' => 'unpaid',
            ],
            'unique' => ['claim_code'],
            'foreign' => [
                'user_id' => ['users', self::CASCADE],
                'location_id' => ['locations', self::RESTRICT],
                'service_id' => ['services', self::RESTRICT],
                'received_by' => ['users', self::SET_NULL],
            ],
        ],
        'sales' => [
            'columns' => [
                'id' => 'int', 'user_id' => 'int', 'print_job_id' => 'int', 'location_id' => 'int',
                'source' => 'string', 'payment_method' => 'string', 'amount' => 'decimal',
                'commission_rate' => 'decimal', 'commission_amount' => 'decimal', 'payout_id' => 'int',
            ] + self::TIMESTAMPS,
            'foreign' => [
                'user_id' => ['users', self::RESTRICT],
                'print_job_id' => ['print_jobs', self::SET_NULL],
                'location_id' => ['locations', self::RESTRICT],
                'payout_id' => ['commission_payouts', self::SET_NULL],
            ],
        ],
        'gcash_payments' => [
            'columns' => [
                'id' => 'int', 'reference_no' => 'string', 'amount' => 'decimal', 'print_job_id' => 'int',
                'sale_id' => 'int', 'received_by' => 'int',
            ] + self::TIMESTAMPS,
            'unique' => ['reference_no'],
            'foreign' => [
                'print_job_id' => ['print_jobs', self::SET_NULL],
                'sale_id' => ['sales', self::CASCADE],
                'received_by' => ['users', self::SET_NULL],
            ],
        ],
        'walkin_items' => [
            'columns' => [
                'id' => 'int', 'sale_id' => 'int', 'service_id' => 'int', 'quantity' => 'int',
                'sheets_used' => 'int', 'unit_price' => 'decimal', 'amount' => 'decimal',
            ] + self::TIMESTAMPS,
            'defaults' => ['sheets_used' => 0],
            'foreign' => ['sale_id' => ['sales', self::CASCADE], 'service_id' => ['services', self::SET_NULL]],
        ],
        'job_logs' => [
            'columns' => [
                'id' => 'int', 'print_job_id' => 'int', 'user_id' => 'int', 'from_status' => 'string',
                'to_status' => 'string', 'note' => 'string',
            ] + self::TIMESTAMPS,
            'foreign' => ['print_job_id' => ['print_jobs', self::CASCADE], 'user_id' => ['users', self::SET_NULL]],
        ],
        'settings' => [
            'columns' => ['key' => 'string', 'value' => 'string'],
            'unique' => ['key'],
        ],
        'announcements' => [
            'columns' => [
                'id' => 'int', 'title' => 'string', 'body' => 'string', 'starts_at' => 'datetime',
                'ends_at' => 'datetime', 'created_by' => 'int',
            ] + self::TIMESTAMPS,
            'foreign' => ['created_by' => ['users', self::SET_NULL]],
        ],
        'notifications' => [
            'columns' => [
                'id' => 'string', 'type' => 'string', 'notifiable_type' => 'string', 'notifiable_id' => 'int',
                'data' => 'string', 'read_at' => 'datetime',
            ] + self::TIMESTAMPS,
        ],
    ];

    public function has(string $table): bool
    {
        return isset(self::TABLES[$table]);
    }

    /**
     * @return list<string>
     */
    public function tables(): array
    {
        return array_keys(self::TABLES);
    }

    /**
     * @return array<string, string> column => type
     */
    public function columns(string $table): array
    {
        return self::TABLES[$table]['columns'] ?? [];
    }

    public function hasColumn(string $table, string $column): bool
    {
        return isset(self::TABLES[$table]['columns'][$column]);
    }

    public function type(string $table, string $column): ?string
    {
        return self::TABLES[$table]['columns'][$column] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(string $table): array
    {
        return self::TABLES[$table]['defaults'] ?? [];
    }

    /**
     * @return list<string>
     */
    public function unique(string $table): array
    {
        return self::TABLES[$table]['unique'] ?? [];
    }

    /**
     * @return array<string, array{0: string, 1: string}> column => [parent table, on delete]
     */
    public function foreignKeys(string $table): array
    {
        return self::TABLES[$table]['foreign'] ?? [];
    }

    /**
     * Child columns that reference the given table.
     *
     * @return list<array{table: string, column: string, onDelete: string}>
     */
    public function referencing(string $parent): array
    {
        $children = [];

        foreach (self::TABLES as $table => $definition) {
            foreach ($definition['foreign'] ?? [] as $column => [$references, $onDelete]) {
                if ($references === $parent) {
                    $children[] = ['table' => $table, 'column' => $column, 'onDelete' => $onDelete];
                }
            }
        }

        return $children;
    }
}
