HumCORE Configuration

Edit the nginx config and add the proxy_pass requests for:
- /deposits/objects/
- /deposits/oai

```sh
# Sample fedora proxy requests
location /deposits/objects/ {
  proxy_buffering on;
  proxy_buffer_size 1k;
  proxy_buffers 48 64k;
  proxy_set_header Host $host;
  proxy_set_header X-Real-IP $remote_addr;
  proxy_pass https://FQDN:8443/fedora/objects/;
}

location /deposits/oai {
  proxy_buffering on;
  proxy_buffer_size 1k;
  proxy_buffers 48 64k;
  proxy_set_header Host $host;
  proxy_set_header X-Real-IP $remote_addr;
  proxy_pass https://FQDN:8443/fedora/oai;
}
```
Restart nginx

Create a directory to contain the uploaded files. NOTE: While the files are stored in fedora, you may want to retain copies of the original submissions.

Example: /var/tmp/humcore

Integrate the template files found in the templates/deposits directory into your theme if you are not using the cbox-mla theme.

Create the collection object.

Log into the WP Admin.

Activate the plugin.

Fill out the settings page.

Create the core page and associated child pages - core terms and core faq. NOTE: there is a template file for each page in the templates/deposits directory.

Add the widgets to the Directory and Search sidebars

- Drag (HumCORE) Deposits Directory Sidebar to the Deposits Directory Sidebar.
- Drag (HumCORE) Faceted Search Results to the Deposits Search Sidebar.

Create the top-level CORE menu entry.

