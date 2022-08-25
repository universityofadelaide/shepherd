
Get kubeadmin credentials:
```
crc console --credentials
```
Let crc open the console, then use the credentials to login to the cluster as kubeadmin
```
crc console
```

* In the web UI, switch to the **shepherd-dev-datagrid** project.
* Switch to the developer perspective.
* Click **+Add** in the top left.
* Click on Helm Chart in the bottom left centre panel.
* Search for 'data' and **Data Grid** should be the first match, click it.
* Or, click this: [Data Grid](https://console-openshift-console.apps-crc.testing/catalog/ns/shepherd-dev-datagrid?catalogType=HelmChart&keyword=data&selectedId=openshift-helm-charts--https%3A%2F%2Fgithub.com%2Fopenshift-helm-charts%2Fcharts%2Freleases%2Fdownload%2Fredhat-data-grid-8.3.1%2Fredhat-data-grid-8.3.1.tgz)
* Click **Install Helm Chart**.
* Leave everything as default and click **Install**.

This should give a basically working data grid installation with defaults.
