<!doctype html>
<html>
  <head>
    <meta charset="utf-8">
    <title>Restablecer contraseña</title>
  </head>
  <body>
    <h2>Restablecer contraseña</h2>

    <p>Hola,</p>

    <p>Recibimos una solicitud para restablecer la contraseña de tu cuenta.</p>

    <p>Hacé clic en el siguiente enlace para establecer una nueva contraseña:</p>

    <p>
      <a href="http://localhost:5173/resetear?token={{ $token }}&email={{ $correo }}">
        Restablecer mi contraseña
      </a>
    </p>

    <p>Si no solicitaste esto, podés ignorar este correo.</p>

    <hr>
    <small>Si el botón no funciona, copialo en tu navegador:</small>
    <p>http://localhost:5173/resetear?token={{ $token }}&email={{ $correo }}</p>
  </body>
</html>
