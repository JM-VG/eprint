<?php

namespace App\Repositories;

class UserRepository extends Repository
{
    public function table(): string
    {
        return 'users';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByStudentId(string $studentId): ?array
    {
        return $this->firstWhere('student_id', $studentId);
    }

    /**
     * Student ids are stored uppercase; lookups are case-insensitive.
     *
     * @return array<string, mixed>|null
     */
    public function findByStudentIdInsensitive(string $studentId): ?array
    {
        $needle = mb_strtoupper(trim($studentId));

        foreach ($this->all() as $user) {
            if (($user['student_id'] ?? null) !== null && mb_strtoupper((string) $user['student_id']) === $needle) {
                return $user;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByUsername(string $username): ?array
    {
        return $this->firstWhere('username', $username);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByLogin(string $login): ?array
    {
        return $this->findByStudentIdInsensitive($login) ?? $this->findByUsername($login);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function withRole(string $role): array
    {
        return $this->whereColumn('role', $role);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function pendingStudents(): array
    {
        return $this->where(
            fn (array $user) => ($user['role'] ?? null) === 'student' && ($user['status'] ?? null) === 'pending',
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function approvedAdmins(): array
    {
        return $this->where(
            fn (array $user) => ($user['role'] ?? null) === 'admin' && ($user['status'] ?? null) === 'approved',
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function staffAndAdmins(): array
    {
        return $this->where(fn (array $user) => in_array($user['role'] ?? null, ['staff', 'admin'], true));
    }
}
