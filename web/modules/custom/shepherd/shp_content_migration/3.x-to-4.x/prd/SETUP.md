
## First create the tokens to automate login:

### oc login to the legacy cluster, then
```
oc get sa | grep shepherd-sa
oc get secret/shepherd-token-kcvvv -o jsonpath='{.data.token}' -n shepherd-prd | base64 -d > LEGACY_TOKEN.txt
```

### Create secret to access s3 bucket.
Copy the secret.yml.dist to secret.yml and insert the appropriate AWS creds.
Then apply the secret so the backup can use it to upload.
```
oc apply -f secret.yml
```

### oc login to the new cluster, then.
```
oc get sa | grep shepherd-sa
oc get secret/shepherd-sa-token-2n7dr -o jsonpath='{.data.token}' -n shepherd-prd | base64 -d > NEW_TOKEN.txt
```

## Perform backup on legacy cluster
This command performs a backup on site 52, environment 223
```
./backup.sh 52 223 208 259
```

## Perform restore on new cluster
This command performs a restore from site 52, environment 223 to new site 208, environment 259
```
./restore.sh 52 223 208 259
```
