{
    "name": "{{{vendor}}}/{{{module}}}",
{{if shorDescription}}
    "description": "{{{shorDescription}}}",
{{endif shorDescription}}
    "keywords": [
        "php",
        "magento",
        "magento2",
        "module",
{{if keywords}}
        "extension"{{{keywords}}}
{{endif keywords}}
    ],
    "require-dev": {
        "magento/magento-coding-standard": "^5",
        "magento/marketplace-eqp": "^4.0",
        "roave/security-advisories": "dev-master"
    },
    "type": "magento2-module",
    "version": "100.0.0",
    "license": [
        "MIT"
    ],
    "autoload": {
        "files": [
            "registration.php"
        ],
        "psr-4": {
            "{{{vendor}}}\\{{{module}}}\\": ""
        }
    },
    "extra": {
        "map": [
            [
                "*",
                "{{{vendor}}}/{{{module}}}"
            ]
        ]
    }
}
