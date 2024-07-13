## Migration

```
drush migrate:upgrade --legacy-db-url="mysql://db:db@atelierhof-kreuzberg-d7:52744/db" --legacy-root=https://atelierhof-kreuzberg-d7.ddev.site --configure-only -l ak 

drush migrate:upgrade --legacy-db-key=ak7 --legacy-root=https://atelierhof-kreuzberg.com --configure-only -l ak 

drush migrate:upgrade --legacy-db-key=ak7 --legacy-root=/var/www/html/ak7 -l ak --configure-only 


```