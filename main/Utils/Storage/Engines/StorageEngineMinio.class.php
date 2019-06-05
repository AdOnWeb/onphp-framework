<?php

class StorageEngineMinio extends StorageEngineHTTP
{
    const ACL_PUBLIC_READ = 'public-read';

    const ERROR_CODE_NO_BUCKET = 'NoSuchBucket';

    /**
     * @var \Aws\S3\S3Client
     */
    private $s3;

    /**
     * @var string
     */
    private $endpoint;

    /**
     * @var string
     */
    private $bucket;

    /**
     * @var string
     */
    private $prefix;

    /**
     * @var string
     */
    private $acl;

    protected function parseConfig($data)
    {
        $this->s3 = new \Aws\S3\S3Client([
            'version'                 => 'latest',
            'region'                  => 'us-east-1',
            'endpoint'                => $data['endpoint'],
            'use_path_style_endpoint' => true,
            'credentials'             => [
                'key'    => $data['access_key_id'],
                'secret' => $data['secret_access_key'],
            ],
        ]);
        $this->endpoint = $data['endpoint'];
        $this->bucket = $data['bucket'];
        $this->prefix = $data['prefix'] ?? null;
        $this->acl = $data['acl'];
        $this->hasHttpLink = true;
    }

    public function getHttpLink($file)
    {
        return $this->prefix
            ? sprintf("%s/%s/%s/%s", $this->endpoint, $this->bucket, $this->prefix, $file)
            : sprintf("%s/%s/%s", $this->endpoint, $this->bucket, $file);
    }

    public function store($file, $desiredName)
    {
        try {
            $result = $this->s3->upload($this->bucket, $this->generateKey($desiredName), $file, $this->acl);
        } catch (\Aws\S3\Exception\S3Exception $exception) {
            if ($exception->getAwsErrorCode() === self::ERROR_CODE_NO_BUCKET) {
                $this->s3->createBucket([
                    'Bucket' => $this->bucket,
                ]);

                $this->s3->putBucketPolicy([
                    'Bucket' => $this->bucket,
                    'Policy' => $this->getPolicy($this->acl),
                ]);

                $result = $this->s3->upload($this->bucket, $this->generateKey($desiredName), $file, $this->acl);
            } else {
                throw $exception;
            }
        }

        $url = $result->get('ObjectURL');

        return $this->prefix
            ? str_replace(sprintf("%s/%s/%s/", $this->endpoint, $this->bucket, $this->prefix), "", $url)
            : str_replace(sprintf("%s/%s/", $this->endpoint, $this->bucket), "", $url);
    }

    protected function unlink($file)
    {
        try {
            $this->s3->deleteObject([
                'Bucket' => $this->bucket,
                'Key'    => $this->generateKey($file),
            ]);
        } catch (\Aws\S3\Exception\S3Exception $exception) {
            return false;
        }

        return true;
    }

    public function copy($from, $to = null)
    {
        if (!$to) {
            throw new InvalidArgumentException('Not implemented');
        }

        $this->s3->copyObject([
            'Bucket'     => $this->bucket,
            'Key'        => $this->generateKey($to),
            'CopySource' => $this->generateKey($from),
        ]);

        return $to;
    }

    public function rename($from, $to)
    {
        $this->copy($this->generateKey($from), $this->generateKey($to));
        $this->unlink($this->generateKey($from));
    }

    private function getPolicy(string $policy)
    {
        switch ($policy) {
            case self::ACL_PUBLIC_READ:
                $policy = [
                    'Version'   => "2012-10-17",
                    'Statement' => [
                        [
                            'Action'    => [ "s3:GetObject" ],
                            'Effect'    => 'Allow',
                            'Principal' => [
                                'AWS' => [ '*' ],
                            ],
                            'Resource'  => [ sprintf('arn:aws:s3:::%s/*', $this->bucket) ],
                            'Sid'       => '',
                        ],
                    ],
                ];

                break;

            default:
                throw new InvalidArgumentException("Not implemented");
        }

        return json_encode($policy);
    }

    /**
     * @param string $file
     *
     * @return string
     */
    private function generateKey(string $file): string
    {
        return $this->prefix
            ? sprintf("%s/%s", $this->prefix, $file)
            : $file;
    }
}
