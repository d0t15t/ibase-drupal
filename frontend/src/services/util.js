import React from "react"
import { graphql } from "gatsby"

const util = {}
export default util

// util.frontpageQuery = () => {
//   return graphql`
//     query frontpageQuery {
//       nodePage(id: {}, drupal_internal__nid: { eq: 1 }) {
//         title
//         body {
//           value
//         }
//         field_hero_text {
//           value
//         }
//         relationships {
//           field_blocks {
//             relationships {
//               field_teasers {
//                 field_headline
//                 relationships {
//                   field_collection {
//                     name
//                     path {
//                       alias
//                     }
//                   }
//                   field_media {
//                     relationships {
//                       field_media_image {
//                         localFile {
//                           url
//                         }
//                       }
//                     }
//                   }
//                 }
//               }
//             }
//           }
//           field_hero_media {
//             relationships {
//               field_media_image {
//                 localFile {
//                   url
//                 }
//               }
//             }
//           }
//         }
//         field_headline
//         field_title_bool
//       }
//     }
//   `
// }
