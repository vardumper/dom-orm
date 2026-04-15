<?php

declare(strict_types=1);

use DOM\ORM\Repository\EntityRepository;
use DOM\ORM\Storage\StorageService;
use DOM\ORM\Traits\EntityManagerTrait;
use Tests\Fixtures\RelComment;
use Tests\Fixtures\RelCompany;
use Tests\Fixtures\RelCourse;
use Tests\Fixtures\RelEmployee;
use Tests\Fixtures\RelEnrollment;
use Tests\Fixtures\RelPost;
use Tests\Fixtures\RelProfile;
use Tests\Fixtures\RelStudent;
use Tests\Fixtures\RelUser;

final class RelationTestEntityManager
{
    use EntityManagerTrait;
}

function relationStorageFile(): string
{
    return getcwd() . '/storage/data.xml';
}

function relationStorageBackupFile(): string
{
    return relationStorageFile() . '.bak';
}

function relationXPath(): DOMXPath
{
    $xml = StorageService::fromConfig()->read();
    $dom = new DOMDocument();
    $loaded = $dom->loadXML($xml);
    expect($loaded)->toBeTrue();

    return new DOMXPath($dom);
}

beforeEach(function (): void {
    $storageFile = relationStorageFile();
    $storageBackup = relationStorageBackupFile();

    if (!is_dir(dirname($storageFile))) {
        mkdir(dirname($storageFile), 0755, true);
    }

    if (file_exists($storageFile)) {
        rename($storageFile, $storageBackup);
    }

    file_put_contents($storageFile, '<data />');
});

afterEach(function (): void {
    $storageFile = relationStorageFile();
    $storageBackup = relationStorageBackupFile();

    if (file_exists($storageFile)) {
        unlink($storageFile);
    }

    if (file_exists($storageBackup)) {
        rename($storageBackup, $storageFile);
    }
});

it('persists one-to-one relation as a single grouped child item', function (): void {
    $manager = new RelationTestEntityManager();
    $manager->persist(new RelUser(
        username: 'alice',
        profile: [new RelProfile('Alice profile', 'profile-1')],
        id: 'user-1',
    ));

    $userRepository = new EntityRepository(RelUser::class);
    $user = $userRepository->find('user-1');
    expect($user)->toBeInstanceOf(RelUser::class);
    /** @var RelUser $user */
    expect($user->getUsername())->toBe('alice');

    $xpath = relationXPath();

    $users = $xpath->query('//item[@type="rel_user"]');
    expect($users)->not->toBeFalse();
    expect($users?->length)->toBe(1);

    $profileItems = $xpath->query('//item[@type="rel_user" and @id="user-1"]/group[@type="profile"]/item[@type="rel_profile"]');
    expect($profileItems)->not->toBeFalse();
    expect($profileItems?->length)->toBe(1);

    $profileBio = $xpath->query('//item[@type="rel_profile" and @id="profile-1"]/fragment[@name="bio"]');
    expect($profileBio)->not->toBeFalse();
    expect($profileBio?->item(0)?->nodeValue)->toBe('Alice profile');
})->group('integration');

it('persists one-to-many relation as grouped child items', function (): void {
    $manager = new RelationTestEntityManager();
    $manager->persist(new RelPost(
        title: 'Post A',
        comments: [
            new RelComment('First comment', 'comment-1'),
            new RelComment('Second comment', 'comment-2'),
            new RelComment('Third comment', 'comment-3'),
        ],
        id: 'post-1',
    ));

    $postRepository = new EntityRepository(RelPost::class);
    $post = $postRepository->find('post-1');
    expect($post)->toBeInstanceOf(RelPost::class);
    /** @var RelPost $post */
    expect($post->getTitle())->toBe('Post A');

    $xpath = relationXPath();

    $commentItems = $xpath->query('//item[@type="rel_post" and @id="post-1"]/group[@type="comments"]/item[@type="rel_comment"]');
    expect($commentItems)->not->toBeFalse();
    expect($commentItems?->length)->toBe(3);
})->group('integration');

it('persists many-to-one relation using shared foreign key fragments', function (): void {
    $manager = new RelationTestEntityManager();

    $manager->persist(new RelCompany('Acme Corp', 'company-1'));
    $manager->persist(new RelCompany('Other Corp', 'company-2'));

    $manager->persist(new RelEmployee('Eve', 'company-1', 'employee-1'));
    $manager->persist(new RelEmployee('Bob', 'company-1', 'employee-2'));
    $manager->persist(new RelEmployee('Zoe', 'company-2', 'employee-3'));

    $employeeRepository = new EntityRepository(RelEmployee::class);
    $employee = $employeeRepository->find('employee-2');
    expect($employee)->toBeInstanceOf(RelEmployee::class);
    /** @var RelEmployee $employee */
    expect($employee->getCompanyId())->toBe('company-1');

    $xpath = relationXPath();

    $linkedToCompanyOne = $xpath->query('//item[@type="rel_employee"][fragment[@name="companyId"]="company-1"]');
    expect($linkedToCompanyOne)->not->toBeFalse();
    expect($linkedToCompanyOne?->length)->toBe(2);
})->group('integration');

it('persists many-to-many relation through enrollment join entities', function (): void {
    $manager = new RelationTestEntityManager();

    $manager->persist(new RelStudent('Alice', 'student-1'));
    $manager->persist(new RelStudent('Bob', 'student-2'));

    $manager->persist(new RelCourse('Math', 'course-1'));
    $manager->persist(new RelCourse('Physics', 'course-2'));

    $manager->persist(new RelEnrollment('student-1', 'course-1', 'enrollment-1'));
    $manager->persist(new RelEnrollment('student-1', 'course-2', 'enrollment-2'));
    $manager->persist(new RelEnrollment('student-2', 'course-1', 'enrollment-3'));
    $manager->persist(new RelEnrollment('student-2', 'course-2', 'enrollment-4'));

    $enrollmentRepository = new EntityRepository(RelEnrollment::class);
    $enrollment = $enrollmentRepository->find('enrollment-3');
    expect($enrollment)->toBeInstanceOf(RelEnrollment::class);
    /** @var RelEnrollment $enrollment */
    expect($enrollment->getStudentId())->toBe('student-2');
    expect($enrollment->getCourseId())->toBe('course-1');

    $xpath = relationXPath();

    $allEnrollments = $xpath->query('//item[@type="rel_enrollment"]');
    expect($allEnrollments)->not->toBeFalse();
    expect($allEnrollments?->length)->toBe(4);

    $studentOneCourses = $xpath->query('//item[@type="rel_enrollment"][fragment[@name="studentId"]="student-1"]');
    expect($studentOneCourses)->not->toBeFalse();
    expect($studentOneCourses?->length)->toBe(2);

    $courseOneStudents = $xpath->query('//item[@type="rel_enrollment"][fragment[@name="courseId"]="course-1"]');
    expect($courseOneStudents)->not->toBeFalse();
    expect($courseOneStudents?->length)->toBe(2);
})->group('integration');
