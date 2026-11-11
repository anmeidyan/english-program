<?php

declare(strict_types=1);

namespace App\Enums;

enum PermissionName: string
{
    case LessonsView = 'lessons.view';
    case LessonsManage = 'lessons.manage';
    case QuestionsManage = 'questions.manage';
    case ExamsTake = 'exams.take';
    case ExamsManage = 'exams.manage';
    case SubmissionsReview = 'submissions.review';
    case UsersManage = 'users.manage';
    case SettingsManage = 'settings.manage';
    case ProgressReset = 'progress.reset';
    case ReportsView = 'reports.view';
}
