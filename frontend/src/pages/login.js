import React from "react"
import { Link } from "gatsby"
import { Card, Form, Input, Button } from "../components/AuthForm"

import Layout from "../components/layout"

const Login = (props) => {
  return (
    <Layout>
      <h1>Login</h1>
      <div>
        <Card>
          <Form>
            <Input type="email" placeholder="email" />
            <Input type="password" placeholder="password" />
            <Button>Sign In</Button>
          </Form>
        </Card>
        <Link to="/">Back to frontpage</Link>
      </div>
    </Layout>
  )
}

export default Login
