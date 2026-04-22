<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;

class InitLocalstack extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:init-localstack';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initialize LocalStack S3 buckets and permissions for OpticVault';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (!app()->environment('local')) {
            $this->error('This command can only be run in the local environment.');
            return Command::FAILURE;
        }

        $bucket = config('filesystems.disks.s3.bucket');
        $this->info("Initializing LocalStack S3 Bucket: {$bucket}...");

        try {
            // Using low-level S3 client to handle creation and ACLs if needed
            $s3 = new S3Client([
                'version' => 'latest',
                'region'  => config('filesystems.disks.s3.region'),
                'endpoint' => config('filesystems.disks.s3.endpoint'),
                'use_path_style_endpoint' => config('filesystems.disks.s3.use_path_style_endpoint'),
                'credentials' => [
                    'key'    => config('filesystems.disks.s3.key'),
                    'secret' => config('filesystems.disks.s3.secret'),
                ],
            ]);

            // Check if bucket exists
            if (!$s3->doesBucketExist($bucket)) {
                $this->comment("Bucket '{$bucket}' does not exist. Creating...");
                $s3->createBucket([
                    'Bucket' => $bucket,
                    'ACL'    => 'public-read', // As requested for graduation project visibility
                ]);
                $this->info("✅ Bucket '{$bucket}' created successfully.");
            } else {
                $this->info("ℹ️ Bucket '{$bucket}' already exists.");
            }

            $this->info("LocalStack initialization complete.");
            return Command::SUCCESS;

        } catch (AwsException $e) {
            $this->error("❌ AWS Error: " . $e->getAwsErrorMessage());
            return Command::FAILURE;
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
