<?php
require_once __DIR__ . '/claves.php';

class Database
{
  private static ?PDO $connection = null;

  public static function getConnection(): PDO
  {
    if (self::$connection === null) {
      self::$connection = self::connect();
    }
    return self::$connection;
  }

  private static function connect(): PDO
  {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    try {
      $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      ]);
      self::autoDeploy($pdo);
      return $pdo;
    } catch (PDOException $e) {
      if ($e->getCode() == 1049) {
        self::crearBaseDeDatos();
        return self::connect();
      }
      die("Error de conexión a la base de datos: " . $e->getMessage());
    }
  }

  private static function crearBaseDeDatos(): void
  {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
  }

  private static function autoDeploy(PDO $pdo): void
  {
    $stmt = $pdo->query(
      "SELECT COUNT(*) FROM information_schema.tables 
        WHERE table_schema = '" . DB_NAME . "' AND table_name = 'categoria'"
    );
    $exists = (int) $stmt->fetchColumn();

    if ($exists === 0) {
      self::createSchema($pdo);
    }
  }

  private static function createSchema(PDO $pdo): void
  {
    $pdo->exec("
      create table if not exists usuario
(
    id     uuid       default gen_random_uuid()         not null,
    nombre varchar(20)                                  not null,
    email  varchar(50)                                  not null,
    pass   varchar(50)                                  not null,
    rol    varchar(5) default 'user'::character varying not null,
    cargo  varchar(50)
);

create unique index if not exists uq_unico_usuario_admin
    on usuario (rol)
    where ((rol)::text = 'admin'::text);

alter table usuario
    add constraint pk_usuario
        primary key (id);

alter table usuario
    add constraint uq_email
        unique (email);

alter table usuario
    add constraint ch_usuario_rol
        check ((rol)::text = ANY ((ARRAY ['user'::character varying, 'admin'::character varying])::text[]));

create policy pc_usuario_select on usuario
    as permissive
    for select
    using (fc_es_admin() OR (id = fc_usuario_actual()));

create policy pc_usuario_delete on usuario
    as permissive
    for delete
    using (fc_es_admin() OR (id = fc_usuario_actual()));

create policy pc_usuario_update on usuario
    as permissive
    for update
    using (fc_es_admin() OR (id = fc_usuario_actual()))
    with check (fc_es_admin() OR (id = fc_usuario_actual()));

create table if not exists curso
(
    id     uuid default gen_random_uuid() not null,
    nombre varchar(20)                    not null
);

alter table curso
    add constraint pk_curso
        primary key (id);

alter table curso
    add constraint uq_curso_nombre
        unique (nombre);

create policy curso_insert on curso
    as permissive
    for insert
    with check (fc_usuario_actual() IS NOT NULL);

create policy curso_update on curso
    as permissive
    for update
    using fc_es_admin()
with check fc_es_admin();

create policy curso_delete on curso
    as permissive
    for delete
    using fc_es_admin();

create table if not exists profesor
(
    id         uuid default gen_random_uuid()   not null,
    id_usuario uuid default fc_usuario_actual() not null,
    nombre     varchar(50)                      not null,
    email      varchar(50),
    telefono   varchar(15),
    detalles   varchar(50)
);

alter table profesor
    add constraint pk_profesor
        primary key (id);

alter table profesor
    add constraint uq_profesor_nombre
        unique (id_usuario, nombre, email);

alter table profesor
    add constraint fk_profesor_usuario
        foreign key (id_usuario) references usuario;

create policy pc_profesor on profesor
    as permissive
    for all
    using (fc_es_admin() OR (id = fc_usuario_actual()))
    with check (fc_es_admin() OR (id = fc_usuario_actual()));

create table if not exists materia
(
    id      uuid default gen_random_uuid() not null,
    nombre  varchar(20)                    not null,
    id_area uuid                           not null
);

alter table materia
    add constraint pk_materia
        primary key (id);

alter table materia
    add constraint uq_materia_nombre
        unique (nombre);

create policy materia_delete on materia
    as permissive
    for delete
    using fc_es_admin();

create policy materia_update on materia
    as permissive
    for update
    using fc_es_admin()
with check fc_es_admin();

create policy materia_insert on materia
    as permissive
    for insert
    with check (fc_usuario_actual() IS NOT NULL);

create table if not exists curso_completo
(
    id               uuid    default gen_random_uuid()   not null,
    id_usuario       uuid    default fc_usuario_actual() not null,
    planificacion    boolean default false               not null,
    diagnostico      boolean default false               not null,
    tpd1             boolean default false               not null,
    tpd2             boolean default false               not null,
    lnck_draiver     varchar(256),
    id_curso_escuela uuid                                not null
);

alter table curso_completo
    add constraint pk_cc
        primary key (id);

alter table curso_completo
    add constraint fk_cc_usuario
        foreign key (id_usuario) references usuario;

create policy pc_curso_completo on curso_completo
    as permissive
    for all
    using (fc_es_admin() OR (id = fc_usuario_actual()))
    with check (fc_es_admin() OR (id = fc_usuario_actual()));

create table if not exists curso_completo_profe
(
    curso_completo uuid not null,
    profe          uuid not null
);

alter table curso_completo_profe
    add constraint pk_cc_profe
        primary key (curso_completo, profe);

alter table curso_completo_profe
    add constraint fk_ccp_curso_completo
        foreign key (curso_completo) references curso_completo;

alter table curso_completo_profe
    add constraint fk_ccp_profe
        foreign key (profe) references profesor;

create table if not exists area
(
    id     uuid default gen_random_uuid() not null,
    nombre varchar(20)                    not null
);

alter table area
    add constraint pk_area
        primary key (id);

alter table materia
    add constraint fk_materia_area
        foreign key (id_area) references area;

alter table area
    add constraint uq_area_nombre
        unique (nombre);

create policy pc_area_delete on area
    as permissive
    for delete
    using fc_es_admin();

create policy pc_area_update on area
    as permissive
    for update
    using fc_es_admin()
with check fc_es_admin();

create policy pc_area_insert on area
    as permissive
    for insert
    with check (fc_usuario_actual() IS NOT NULL);

create table if not exists escuela
(
    id     uuid default gen_random_uuid() not null,
    nombre varchar(50)                    not null
);

alter table escuela
    add constraint pk_escuela
        primary key (id);

alter table escuela
    add constraint uq_escuela_nombre
        unique (nombre);

create policy pc_escuela_delete on escuela
    as permissive
    for delete
    using fc_es_admin();

create policy pc_escuela_update on escuela
    as permissive
    for update
    using fc_es_admin()
with check fc_es_admin();

create policy pc_escuela_insert on escuela
    as permissive
    for insert
    with check (fc_usuario_actual() IS NOT NULL);

create table if not exists curso_escuela
(
    id            uuid default gen_random_uuid() not null,
    id_escuela    uuid                           not null,
    id_curso      uuid                           not null,
    id_materia    uuid                           not null,
    carga_horaria numeric(4, 2),
    anio          char(4)
);

alter table curso_escuela
    add constraint pk_curso_escuela
        primary key (id);

alter table curso_completo
    add constraint fk_cc_curso_escuela
        foreign key (id_curso_escuela) references curso_escuela;

alter table curso_escuela
    add constraint uq_ce_materia
        unique (id_escuela, id_curso, id_materia, anio);

alter table curso_escuela
    add constraint fk_ce_curso
        foreign key (id_curso) references curso;

alter table curso_escuela
    add constraint fk_ce_escuela
        foreign key (id_escuela) references escuela;

alter table curso_escuela
    add constraint fk_ce_materia
        foreign key (id_materia) references materia;

create policy pc_curso_escuela_insert on curso_escuela
    as permissive
    for insert
    with check (fc_usuario_actual() IS NOT NULL);

create policy pc_curso_escuela_update on curso_escuela
    as permissive
    for update
    using fc_es_admin()
with check fc_es_admin();

create policy pc_curso_escuela_delete on curso_escuela
    as permissive
    for delete
    using fc_es_admin();

create table if not exists dia
(
    id     uuid default gen_random_uuid() not null,
    nombre varchar(10)
);

alter table dia
    add constraint pk_dia
        primary key (id);

alter table dia
    add constraint uq_nombre
        unique (nombre);

alter table dia
    add constraint chk_nombre
        check ((nombre)::text = ANY
               ((ARRAY ['lunes'::character varying, 'martes'::character varying, 'miercoles'::character varying, 'jueves'::character varying, 'viernes'::character varying, 'sabado'::character varying, 'domingo'::character varying])::text[]));

create policy pc_dia_update on dia
    as permissive
    for update
    using fc_es_admin()
with check fc_es_admin();

create policy pc_dia_delete on dia
    as permissive
    for delete
    using fc_es_admin();

create policy pc_dia_insert on dia
    as permissive
    for insert
    with check fc_es_admin();

create table if not exists curso_escuela_dia
(
    id_dia           uuid not null,
    id_curso_escuela uuid not null,
    hora_entrada     time,
    hora_salida      time
);

alter table curso_escuela_dia
    add constraint pk_curso_escuela_dia
        primary key (id_curso_escuela, id_dia);

alter table curso_escuela_dia
    add constraint fk_ced_dia
        foreign key (id_dia) references dia;

alter table curso_escuela_dia
    add constraint fk_ced_escuela
        foreign key (id_curso_escuela) references curso_escuela;

create policy pc_ced_insert on curso_escuela_dia
    as permissive
    for insert
    with check fc_es_admin();

create policy pc_ced_update on curso_escuela_dia
    as permissive
    for update
    using fc_es_admin()
with check fc_es_admin();

create policy pc_ced_delete on curso_escuela_dia
    as permissive
    for delete
    using fc_es_admin();

create or replace function fc_usuario_actual() returns uuid
    stable
    language plpgsql
as
$$
BEGIN
    RETURN current_setting('app.user_id', true)::UUID;
    EXCEPTION WHEN OTHERS THEN RETURN NULL;
END;
$$;

create or replace function fc_cargar_contexto_usuario() returns void
    security definer
    language plpgsql
as
$$
DECLARE
    v_rol     VARCHAR(20);
BEGIN
    -- Si ya fue cargado en esta transacción, no hacer nada
    IF current_setting('app.contexto_cargado', true) = 'true' THEN
        RETURN;
    END IF;

    SELECT rol
    INTO v_rol
    FROM usuario
    WHERE id = fc_usuario_actual();

    IF NOT FOUND THEN
        RAISE EXCEPTION 'Usuario % no encontrado', fc_usuario_actual();
    END IF;

    -- Guardar en GUCs locales (duran solo la transacción con SET LOCAL)
    PERFORM set_config('app.rol',        v_rol,                true);
    PERFORM set_config('app.contexto_cargado', 'true',         true);
END;
$$;

create or replace function fc_es_admin() returns boolean
    stable
    language plpgsql
as
$$
BEGIN
    PERFORM fc_cargar_contexto_usuario();
    RETURN current_setting('app.rol', true) IN ('admin');
END;
$$;

create or replace function fc_login(p_nombre character varying, p_pass character varying)
    returns TABLE(id uuid, nombre character varying, rol character varying)
    security definer
    language plpgsql
as
$$
DECLARE
    v_id UUID;
    v_rol varchar;
BEGIN
    select u.id, u.rol
    INTO v_id, v_rol
    from usuario u where u.nombre = p_nombre and u.pass = p_pass;
    IF v_id IS NULL THEN
        RAISE EXCEPTION 'Error al tratar de iniciar sesión. El usuario o la contraseña son incorrectas';
    END IF;
     PERFORM set_config('app.user_id', v_id::text, false);
    RETURN QUERY
    SELECT v_id, p_nombre, v_rol;
END;
$$;


  ");
  }

}
