<?php

class StorageEngineMinio extends StorageEngineHTTP
{
    const ACL_PUBLIC_READ = 'public-read';
    const ACL_PRIVATE = 'private';

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
        $this->prefix = $data['prefix'] ? trim($data['prefix'], "/") : null;
        $this->acl = $data['acl'];
        $this->hasHttpLink = true;
    }

    public function getHttpLink($file)
    {
        return $this->prefix
            ? sprintf("%s/%s/%s/%s", $this->endpoint, $this->bucket, $this->prefix, $file)
            : sprintf("%s/%s/%s", $this->endpoint, $this->bucket, $file);
    }

    /**
     * @param string $file
     * @param mixed $expires максимум 7 дней по спецификации
     *
     * @return string
     * @see S3ClientInterface::createPresignedRequest
     *
     */
    public function getSignedHttpLink(string $file, $expires)
    {
        $command = $this->s3->getCommand('GetObject', [
            'Bucket' => $this->bucket,
            'Key'    => $this->generateKey($file),
        ]);

        $presignedRequest = $this->s3->createPresignedRequest($command, $expires);

        return (string)$presignedRequest->getUri();
    }

    public function store($file, $desiredName)
    {
        $stream = new \GuzzleHttp\Psr7\LazyOpenStream($file, 'r+');

        $options = [];

        $mime = mime_content_type($file);
        if ($mime) {
            $options['params'] = [
                'ContentType' => $mime,
            ];
        }

        $result = $this->s3->upload($this->bucket, $this->generateKey($desiredName), $stream, $this->acl, $options);
        $stream->close();
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

    /**
     * @param string $file
     *
     * @return string
     */
    private function generateKey(string $file): string
    {
        $file = trim($file, "/");
        return $this->prefix
            ? sprintf("%s/%s", $this->prefix, $file)
            : $file;
    }
}
