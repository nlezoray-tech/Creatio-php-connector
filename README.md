Creatio PHP Connector

PHP connector (Guzzle) for Creatio with two access modes:
OAuth (retrieving a token from the Identity Server then making OData calls with Bearer),
OData (direct access to Creatio's OData API).

Installation
composer require nlezoray/creatio-php-connector

Files to edit
1) OAuth : src/Creatio/Adapter/CreatioOAuthAdapter.php
2) OData : src/Creatio/Adapter/CreatioODataAdapter.php

php examples/basic.php

Licence
MIT. See the LICENSE file.

Contributions
Issues and PRs welcome. Please do not include secrets in examples or tests.