Shepherd reports
================

Reports live under admin/reports.

Populating the version labels in OpenShift requires running a script:

```bash
for pod in $(oc get pods --show-all=false --no-headers --selector='environment_id' | awk '{print $1}'); do  
oc label pod $pod version=$(oc exec $pod cat /code/web/.shepherd/git-tags);  
done
```
