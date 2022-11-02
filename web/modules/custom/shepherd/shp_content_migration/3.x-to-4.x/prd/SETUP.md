
## First create the tokens to automate login:

### oc login to the legacy cluster, then
```
oc get sa | grep shepherd-sa
oc get secret/shepherd-token-kcvvv -o jsonpath='{.data.token}' -n shepherd-prd | base64 -d > LEGACY_TOKEN.txt
```

### Create secret to access s3 bucket if it doesn't already exist.
Check if it already exists:
```
oc get secret openshift-4-migration
```
https://thycotic.ad.adelaide.edu.au/app/#/secret/5989/general
Copy the secret.yml.dist to secret.yml and insert the appropriate AWS creds.
Then apply the secret so the backup can use it to upload.
```
oc apply -f secret.yml
```

### oc login to the new cluster, then.
```
oc get sa | grep shepherd-sa
oc get secret/shepherd-sa-token-9qvvr -o jsonpath='{.data.token}' -n shepherd-prd | base64 -d > NEW_TOKEN.txt
```

### Test that your logins are working to legacy and new OpenShift clusters

```
./legacy_login.sh
./new_login.sh
```
