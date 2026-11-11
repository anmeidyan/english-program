<?php

declare(strict_types=1);

namespace App\Enums;

enum RoleName: string
{
    case Student = 'student';
    case Reviewer = 'reviewer';
    case ContentAdmin = 'content_admin';
    case SuperAdmin = 'super_admin';

    /** @return list<PermissionName> */
    public function permissions(): array
    {
        return match ($this) {
            self::Student => [PermissionName::LessonsView, PermissionName::ExamsTake],
            self::Reviewer => [PermissionName::LessonsView, PermissionName::SubmissionsReview, PermissionName::ReportsView],
            self::ContentAdmin => [
                PermissionName::LessonsView, PermissionName::LessonsManage, PermissionName::QuestionsManage,
                PermissionName::ExamsManage, PermissionName::ReportsView,
            ],
            self::SuperAdmin => PermissionName::cases(),
        };
    }

    /** @return list<string> */
    public static function staff(): array
    {
        return [self::Reviewer->value, self::ContentAdmin->value, self::SuperAdmin->value];
    }
}
