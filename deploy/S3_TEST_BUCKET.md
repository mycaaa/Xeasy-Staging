# Bill and odometer files on the test host (S3)

Testers upload bills and odometer photos. On the test host these files must go to a **separate test bucket**,
never to the live bucket, and the bucket must not be readable from the internet.

## 1. Before anything else: retire the exposed key

The old PHP file `S3Service_New.php` carries an AWS access key and secret written into the code. Anyone who has
had the file (server, backups, mail, downloads) can use that key on the live bucket.

1. In AWS IAM, create a new key for whatever still needs one (or move it to an instance role, step 3).
2. Put the new key in the server settings (step 4), not in code.
3. Deactivate the old key, watch the old system for a day, then delete it.
4. In CloudTrail, look at what the old key did recently (unexpected IPs, `ListBucket`, `GetObject` in bulk).

`S3Service_New_secure.php` (delivered with this note) is the same class without the key in it.

## 2. The test bucket (AWS console, once)

| Setting | Value |
|---|---|
| Name | e.g. `vnr-xeasy-test` (region ap-south-1) |
| Block Public Access | **all four on** |
| Object Ownership | **Bucket owner enforced** (ACLs disabled; `public-read` uploads are refused) |
| Default encryption | SSE-S3 |
| Versioning | on (a wrong delete during testing can be undone) |
| Lifecycle | delete objects after 180 days (test data) |
| CORS | none needed (the app uploads from the server, browsers open signed links) |

Optional: copy a small sample of live folders into it for realistic testing (`aws s3 sync` with a read-only
profile on live and the test profile on the target). Do not copy whole years.

## 3. Access for the test host (least privilege)

Create an IAM **user** `xeasy-test-app` (or, if the test host runs on EC2, an **instance role**, then no key at all)
with only this policy:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "AppFolders",
      "Effect": "Allow",
      "Action": ["s3:GetObject", "s3:PutObject", "s3:DeleteObject"],
      "Resource": [
        "arn:aws:s3:::vnr-xeasy-test/Expense/*",
        "arn:aws:s3:::vnr-xeasy-test/VVNR_Expense/*",
        "arn:aws:s3:::vnr-xeasy-test/xeasy/*",
        "arn:aws:s3:::vnr-xeasy-test/xeasy_vvnr/*",
        "arn:aws:s3:::vnr-xeasy-test/xeasy-healthcheck/*"
      ]
    },
    {
      "Sid": "ListOwnFolders",
      "Effect": "Allow",
      "Action": "s3:ListBucket",
      "Resource": "arn:aws:s3:::vnr-xeasy-test",
      "Condition": { "StringLike": { "s3:prefix": ["Expense/*", "VVNR_Expense/*", "xeasy/*", "xeasy_vvnr/*", "xeasy-healthcheck/*"] } }
    }
  ]
}
```

No `s3:*`, no `PutObjectAcl`, no access to any other bucket. The live bucket's key and the test key are never
the same key.

## 4. Settings on the test host (`.env`, never in git)

```
CLAIM_FILES_LOCAL=false
AWS_ACCESS_KEY_ID=<key of xeasy-test-app>      # leave empty with an EC2 instance role
AWS_SECRET_ACCESS_KEY=<its secret>
AWS_DEFAULT_REGION=ap-south-1
AWS_BUCKET=vnr-xeasy-test
AWS_PRIVATE_BUCKET=true      # files are shown through signed links that expire
AWS_URL_MINUTES=60
```

`chmod 640 .env`, owner the deploy user, group the web user.

For the old PHP screens on the same test server (if they are used), the secured `S3Service_New_secure.php`
reads the same kind of settings from the environment or from `/etc/xeasy/s3.php` (outside the web folder):

```php
<?php return [
    'XEASY_S3_BUCKET' => 'vnr-xeasy-test',
    'XEASY_S3_REGION' => 'ap-south-1',
    'XEASY_S3_KEY' => '...',        // leave out with an instance role
    'XEASY_S3_SECRET' => '...',
];
```
(`chmod 600`, owner the web server user.)

## 5. Prove it before testers start

```
php artisan config:clear
php artisan files:s3-check
```

It refuses the live bucket on a non-production app, writes a small file, reads it back, checks it is **not**
readable without credentials, checks the app's view link works, and deletes the file. Expected end:
`Bucket ready for testing.`

Then upload one bill from the web and one from the app and open both.

## 6. Moving to live later

Live keeps its own bucket and its own key (or role). The same settings apply: key in the environment only, Block
Public Access on, `AWS_PRIVATE_BUCKET=true`. Existing live files were uploaded `public-read`; once every screen
opens files through signed links (this release does for the new app; the old PHP screens need the secured
`S3Service_New`), turn on Block Public Access on the live bucket.
