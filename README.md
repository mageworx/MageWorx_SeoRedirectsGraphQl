# MageWorx_SeoRedirectsGraphQl

GraphQL API module for Mageworx [Magento 2 SEO Suite Ultimate](https://www.mageworx.com/magento-2-seo-extension.html) extension. 

## Installation
**1) Copy-to-paste method**
- Download this module and upload it to the `app/code/MageWorx/SeoRedirectsGraphQl` directory *(create "SeoRedirectsGraphQl" first if missing)*

**2) Installation using composer (from packagist)**
- Execute the following command: `composer require mageworx/module-seoredirects-graph-ql`

## How to use

**[SeoRedirectsGraphQl](https://github.com/mageworx/MageWorx_SeoRedirectsGraphQl)** module modifies the current values of the existing Output attributes for [urlResolver query](https://devdocs.magento.com/guides/v2.4/graphql/queries/url-resolver.html) if there is at least 1 Mageworx redirect for the 'requested URL' entity.

This module is compatible with:
<ul>
        <li>redirects for deleted products</li>
        <li>custom redirects with Request Entity Type and Target Entity Type equal to Product, Category or CMS Page</li>
</ul>

For example, urlResolver query has the following syntax:

```
{urlResolver(url: String!): EntityUrl}
```

**Request:**

```
{
  urlResolver(url: "savvy-shoulder-tote.html") {
    id
    relative_url
    redirectCode
    type
  }
}
```

**Response:**

```
{
  "data": {
    "urlResolver": {
      "id": 2047,
      "relative_url": "erika-running-short.html",
      "redirectCode": 301,
      "type": "PRODUCT"
    }
  }
}
```
