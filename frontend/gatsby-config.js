const path = require("path")

const drupalSourceUrl = "http://admin.ibase.test"
const apiBaseSuffix = "/jsonapi"

// const drupalSourceUrl = process.env.GATSBY_DRUPAL_SOURCE
// const apiBaseSuffix = process.env.GATSBY_API_ENDPOINT
const drupalSourceApiUrl = drupalSourceUrl + apiBaseSuffix
// console.log(drupalSourceApiUrl)

module.exports = {
  siteMetadata: {
    title: "Goldies Dev",
    slogan: "Gatsby Drupal source innit.",
  },
  plugins: [
    "gatsby-plugin-react-helmet",
    {
      resolve: `gatsby-source-filesystem`,
      options: {
        name: `images`,
        path: `${__dirname}/src/images`,
      },
    },
    {
      resolve: "gatsby-source-drupal",
      options: {
        baseUrl: drupalSourceUrl,
        apiBase: apiBaseSuffix,
        links: {
          pages: `${drupalSourceApiUrl}/node/page`,
          products: `${drupalSourceApiUrl}/shopify_product/shopify_product`,
          collections: `${drupalSourceApiUrl}/taxonomy_term/shopify_collections`,
        },
      },
    },
    "gatsby-transformer-sharp",
    "gatsby-plugin-sharp",
    // {
    //   resolve: `gatsby-plugin-manifest`,
    //   options: {
    //     name: 'gatsby-starter-default',
    //     short_name: 'starter',
    //     start_url: '/',
    //     background_color: '#663399',
    //     theme_color: '#663399',
    //     display: 'minimal-ui',
    //     icon: 'src/images/gatsby-icon.png', // This path is relative to the root of the site.
    //   },
    // },
    // this (optional) plugin enables Progressive Web App + Offline functionality
    // To learn more, visit: https://gatsby.app/offline
    // 'gatsby-plugin-offline',
  ],
}
