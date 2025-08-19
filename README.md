Creatio PHP Connector

Connecteur PHP (Guzzle) pour Creatio avec deux modes d’accès :
OAuth (récupération d’un token auprès de l’Identity Server puis appels OData avec Bearer),
OData (accès direct à l’API OData de Creatio).

Installation
composer require nlezoray/creatio-php-connector

Fichiers à modifier
1) OAuth : src/Creatio/Adapter/CreatioOAuthAdapter.php
2) OData : src/Creatio/Adapter/CreatioODataAdapter.php

php examples/basic.php

Licence
MIT. Voir le fichier LICENSE.

Contributions
Issues et PR bienvenues. Merci de ne pas inclure de secrets dans les exemples ou tests.