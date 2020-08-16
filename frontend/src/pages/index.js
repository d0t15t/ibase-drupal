import React from "react"
import { useStaticQuery, Link } from "gatsby"
import renderMarkup from "react-render-markup"
import util from "~services/util"
import { Card, Logo, Form, Input, Button } from "../components/AuthForm"

import Layout from "../components/layout"
// import Image from '../components/image'
const Frontpage = () => {
  const data = useStaticQuery()
  const { nodePage } = data
  const { title, body } = nodePage

  return (
    <Layout>
      <h1>{title}</h1>
      {/* <h3>langcode: {langcode}</h3> */}
      <div>
        {/* <small>Markup from Drupal: </small> */}
        <>{renderMarkup(body.value)}</>
        <Link to="/login">Login</Link>
      </div>
    </Layout>
  )
}

export default Frontpage
