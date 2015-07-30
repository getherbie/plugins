Subtree Split
=============

Thanks to https://github.com/dflydev/git-subsplit for automating and simplifying
the process of managing one-way read-only subtree splits.

    $ git subsplit init https://github.com/getherbie/plugins.git

    $ git subsplit update

    $ git subsplit publish "
        adminpanel:https://github.com/getherbie/plugin-adminpanel.git
        disqus:https://github.com/getherbie/plugin-disqus.git
        feed:https://github.com/getherbie/plugin-feed.git
        form:https://github.com/getherbie/plugin-form.git
        gist:https://github.com/getherbie/plugin-gist.git
        grid:https://github.com/getherbie/plugin-grid.git
        googlemaps:https://github.com/getherbie/plugin-googlemaps.git
        highlight:https://github.com/getherbie/plugin-highlight.git
        imagine:https://github.com/getherbie/plugin-imagine.git
        lipsum:https://github.com/getherbie/plugin-lipsum.git
        random:https://github.com/getherbie/plugin-random.git
        redirect:https://github.com/getherbie/plugin-redirect.git
        shortcode:https://github.com/getherbie/plugin-shortcode.git
        simplecontact:https://github.com/getherbie/plugin-simplecontact.git
        simplesearch:https://github.com/getherbie/plugin-simplesearch.git
        test:https://github.com/getherbie/plugin-test.git
        video:https://github.com/getherbie/plugin-video.git
        xmlsitemap:https://github.com/getherbie/plugin-xmlsitemap.git
    " --heads=master
