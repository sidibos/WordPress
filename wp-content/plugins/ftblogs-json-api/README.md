FT Blogs JSON API WordPress Plugin
========

##Plugin Signature
* **Plugin Name:** FT Blogs JSON API
* **Plugin URI:** <http://blogs.ft.com>
* **Original Plugin URI:** <http://wordpress.org/plugins/json-api/>
* **Description:** A RESTful API for WordPress modified for FT Help purposes to expose pages like T&Cs, Cookie & Privacy policy, etc via a JSON API
* **Author:** FT Blogs
* **Original Author:**  Dan Phiffer
* **Author URI:** <http://blogs.ft.com/>
* **Original Author URI:** <http://phiffer.org/>
* **Version:** 1.0
* **Original Version:** 1.1.1
* **Network:** false

##Technical notes
Plugin exposes **Pages** via a RESTful read-only API.

###Modifications to the original plugin
The original WP plugin provides various different endpoints to read from and write to the WP (Posts, Comments, Widgets, Search, Categories, Archive, etc).
The FT modifications removed all unnecessary controllers except for the ```Core``` controller and also removed all methods except for ```get_page``` from the ```Core``` controller. The original controllers are still available in ```controllers/original/``` directory for future reference.

###Settings
The plugin utilises controller classes to group different endpoint methods and allows activate/deactivate each controller via the ```WP Admin > Settings > JSON API``` screen.
In the settings, you can also configure the *API Base* (```api``` by default) that will be added to the WP Rewrites.

###Accessing the API
One can access the API by either adding ```?json=1``` query string to the original URL, or using the *API Base* and specify the method and parameters. Using the second approach, you can use the slug, ID, page slug, etc.

For example, the following will return equivalent result:

* <http://help.ft.com/tools-services/ft-com-terms-and-conditions/?json=1>
* <http://help.ft.com/api/get_page/?slug=tools-services/ft-com-terms-and-conditions/>

###Response
The response will be in a valid JSON format, that will contain ```page``` object, e.g.:

    {
        status: "ok",
        page: {
            id: 15492,
            type: "page",
            slug: "ft-com-terms-and-conditions",
            url: "http://janmajek.sandboxes.help.ft.com/tools-services/ft-com-terms-and-conditions/",
            status: "publish",
            title: "FT.com terms and conditions",
            title_plain: "FT.com terms and conditions",
            content: "...Content (containing HTML)...",
            excerpt: "...Excerpt (containing HTML)...",
            date: "2013-07-12 10:32:49",
            modified: "2013-09-02 15:34:52",
            categories: [ ],
            tags: [ ],
            author: {
                id: 1441,
                slug: "alexanderwalters",
                name: "FT Help",
                first_name: "FT",
                last_name: "Help",
                nickname: "alexanderwalters",
                url: "",
                description: ""
            },
            comments: [ ],
            attachments: [ ],
            comment_count: 0,
            comment_status: "closed",
            custom_fields: {
                assanka_atompush: [
                    "yes_atompush"
                ],
                assanka_navigationoptions_display: [
                    "do_not_display_in_menu"
                ]
            }
        }
    }

##Useful links
* [Original Plugin](https://wordpress.org/plugins/json-api/)
* [Original Documentation](https://wordpress.org/plugins/json-api/other_notes/)
* [WP Rewrite documentation](http://codex.wordpress.org/Class_Reference/WP_Rewrite)
* [US56943](https://rally1.rallydev.com/#/18948172876ud/detail/userstory/21051889551)