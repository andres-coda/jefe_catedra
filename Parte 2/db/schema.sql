-- ============================================================================
-- db/schema.sql — Multi-School Platform (Jefe de Cátedra) — ESQUEMA DEFINITIVO
-- ----------------------------------------------------------------------------
-- Fuente de verdad del esquema: definición entregada por el propietario +
-- ajustes acordados (rol, fecha_cese, anio) + RLS completo y operativo.
--
-- Aplica UNA sola vez sobre una base recién recreada (NO es idempotente):
--     DROP SCHEMA public CASCADE; CREATE SCHEMA public;      -- como superusuario
--     psql -U <superusuario> -h localhost -d jefe_catedra -f db/schema.sql
-- Destructivo: pierde los datos actuales (datos de prueba). Confirmar antes.
--
-- ORDEN DEL ARCHIVO (importante): las policies referencian las funciones
-- auxiliares, y PostgreSQL valida su existencia al CREAR cada policy. Por eso
-- el orden es: 1) tablas + constraints, 2) funciones, 3) policies, 4) RLS, 5) grants.
--
-- Modelo de seguridad:
--   - RLS ACTIVO: todas las tablas tienen ENABLE ROW LEVEL SECURITY.
--   - app_role (único cliente de la app) NO es owner de las tablas => las
--     policies lo afectan (no hace falta FORCE ROW LEVEL SECURITY).
--   - El owner (superusuario que aplica el archivo) bypasea RLS: ahí se crean
--     el admin global y cualquier dato de mantenimiento.
--   - El usuario se carga por sesión: la app ejecuta sobre la conexión
--     SELECT set_config('app.user_id', '<uuid>', false) al conectar
--     (app/Core/Database.php). Sin esa llamada, la sesión queda anónima.
--   - fc_cargar_contexto_usuario() vuelca rol global + escuelas del usuario a
--     GUCs transaccionales; las policies consultan GUCs, no tablas.
--   - fc_es_admin_directivo_jefe() y fc_es_jefe_x_curso_escuela() leen
--     curso_escuela/materia y son SECURITY DEFINER para evitar denegación
--     circular con RLS activo.
--   - Nuevo helper fc_es_admin_directivo_jefe_escuela(p_id_escuela): corrige
--     las policies de profesor, que recibían id_escuela donde el helper
--     original esperaba id_curso_escuela (denegaba a directivo/jefe).
--   - fc_autenticar(p_email) es el ÚNICO acceso a `usuario` disponible para el
--     visitante anónimo. pc_usuario_select exige admin/self/directivo, contexto
--     que sólo existe DESPUÉS de autenticar, así que el login no podía leer su
--     propia fila (huevo y gallina). Corre como owner (bypasea RLS) y devuelve
--     sólo id/nombre/email/pass/rol para un email: no ensancha ninguna policy.
--
-- Requiere PostgreSQL 13+ (gen_random_uuid() nativo).
-- ============================================================================

-- ============================================================================
-- 1) TABLAS + CONSTRAINTS
-- ============================================================================

-- ----------------------------------------------------------------------------
-- usuario
-- ----------------------------------------------------------------------------
create table if not exists usuario
(
    id        uuid                     default gen_random_uuid()         not null,
    nombre    varchar(20)                                                not null,
    email     varchar(50)                                                not null,
    pass      varchar(255)                                               not null,
    rol       varchar(5)               default 'user'::character varying not null,
    creado_en timestamp with time zone default now()                     not null
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

-- Corrección acordada (override): rol GLOBAL es solo 'user' | 'admin'.
-- 'jefe'/'directivo' viven en escuela_usuario (rol por escuela).
alter table usuario
    add constraint ch_usuario_rol
        check ((rol)::text = ANY
               ((ARRAY ['user'::character varying, 'admin'::character varying])::text[]));

-- ----------------------------------------------------------------------------
-- curso
-- ----------------------------------------------------------------------------
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

-- ----------------------------------------------------------------------------
-- profesor — registro del docente por escuela
-- ----------------------------------------------------------------------------
create table if not exists profesor
(
    id         uuid default gen_random_uuid() not null,
    nombre     varchar(50)                    not null,
    email      varchar(50),
    telefono   varchar(15),
    detalles   varchar(50),
    id_escuela uuid                           not null
);

alter table profesor
    add constraint pk_profesor
        primary key (id);

alter table profesor
    add constraint uq_profesor_escuela
        unique (id_escuela, email);

-- ----------------------------------------------------------------------------
-- materia
-- ----------------------------------------------------------------------------
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

-- ----------------------------------------------------------------------------
-- area
-- ----------------------------------------------------------------------------
create table if not exists area
(
    id     uuid default gen_random_uuid() not null,
    nombre varchar(80)                    not null
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

-- ----------------------------------------------------------------------------
-- escuela
-- ----------------------------------------------------------------------------
create table if not exists escuela
(
    id            uuid       default gen_random_uuid()        not null,
    nombre        varchar(50)                                 not null,
    numero        integer                                     not null,
    sigla         varchar(5) default 'EES'::character varying not null,
    anexo         varchar(9),
    sector        varchar(1) default '0'::character varying   not null,
    region        integer                                     not null,
    distrito      varchar(50)                                 not null,
    localidad     varchar(50)                                 not null,
    direccion     varchar(100)                                not null,
    codigo_postal varchar(5)                                  not null,
    telefono      varchar(15),
    email         varchar(50)
);

alter table escuela
    add constraint pk_escuela
        primary key (id);

alter table profesor
    add constraint fk_id_escuela
        foreign key (id_escuela) references escuela;

alter table escuela
    add constraint uq_escuela_nombre
        unique (nombre);

alter table escuela
    add constraint chk_escuela_sector
        check ((sector)::text = ANY
               ((ARRAY ['0'::character varying, '1'::character varying, '2'::character varying, '3'::character varying])::text[]));

-- ----------------------------------------------------------------------------
-- curso_escuela — curso dictado en una escuela (curso + materia + anio)
-- ----------------------------------------------------------------------------
create table if not exists curso_escuela
(
    id            uuid default gen_random_uuid() not null,
    id_escuela    uuid                           not null,
    id_curso      uuid                           not null,
    id_materia    uuid                           not null,
    carga_horaria numeric(4, 2),
    anio          smallint
);

alter table curso_escuela
    add constraint pk_curso_escuela
        primary key (id);

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

-- ----------------------------------------------------------------------------
-- dia
-- ----------------------------------------------------------------------------
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

-- ----------------------------------------------------------------------------
-- curso_escuela_dia — horarios por curso dictado
-- ----------------------------------------------------------------------------
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

-- ----------------------------------------------------------------------------
-- orientacion
-- ----------------------------------------------------------------------------
create table if not exists orientacion
(
    id     uuid default gen_random_uuid() not null,
    nombre varchar(50)                    not null
);

alter table orientacion
    add constraint pk_orientacion
        primary key (id);

alter table orientacion
    add constraint uq_nombre_orientacion
        unique (nombre);

-- ----------------------------------------------------------------------------
-- turno
-- ----------------------------------------------------------------------------
create table if not exists turno
(
    id     uuid default gen_random_uuid() not null,
    nombre varchar(10)                    not null
);

alter table turno
    add constraint pk_turno
        primary key (id);

alter table turno
    add constraint uq_nombre_turno
        unique (nombre);

alter table turno
    add constraint chk_nombre_turno
        check ((nombre)::text = ANY
               ((ARRAY ['Mañana'::character varying, 'Tarde'::character varying, 'Noche'::character varying])::text[]));

-- ----------------------------------------------------------------------------
-- escuela_turno
-- ----------------------------------------------------------------------------
create table if not exists escuela_turno
(
    id_escuela uuid not null,
    id_turno   uuid not null
);

alter table escuela_turno
    add constraint pk_escuela_turno
        primary key (id_escuela, id_turno);

alter table escuela_turno
    add constraint fk_et_escuela
        foreign key (id_escuela) references escuela;

alter table escuela_turno
    add constraint fk_et_turno
        foreign key (id_turno) references turno;

-- ----------------------------------------------------------------------------
-- escuela_orientacion
-- ----------------------------------------------------------------------------
create table if not exists escuela_orientacion
(
    id_escuela     uuid not null,
    id_orientacion uuid not null
);

alter table escuela_orientacion
    add constraint pk_escuela_orinetacion
        primary key (id_escuela, id_orientacion);

alter table escuela_orientacion
    add constraint fk_eo_escuela
        foreign key (id_escuela) references escuela;

alter table escuela_orientacion
    add constraint fk_eo_orientacion
        foreign key (id_orientacion) references orientacion;

-- ----------------------------------------------------------------------------
-- tarea — tareas por curso dictado, con flag "realizado"
-- ----------------------------------------------------------------------------
create table if not exists tarea
(
    id               uuid    default gen_random_uuid() not null,
    id_curso_escuela uuid                              not null,
    descripcion      varchar(50)                       not null,
    observaciones    varchar(255),
    realizado        boolean default false             not null
);

alter table tarea
    add constraint pk_tarea
        primary key (id);

alter table tarea
    add constraint fk_tarea_curso_escuela
        foreign key (id_curso_escuela) references curso_escuela
            on delete cascade;

-- ----------------------------------------------------------------------------
-- escuela_usuario — rol del usuario DENTRO de una escuela (pivot)
-- ----------------------------------------------------------------------------
create table if not exists escuela_usuario
(
    id         uuid default gen_random_uuid() not null,
    id_escuela uuid                           not null,
    id_usuario uuid                           not null,
    rol        varchar(10)                    not null,
    id_area    uuid
);

alter table escuela_usuario
    add constraint pk_escuela_usuario
        primary key (id);

alter table escuela_usuario
    add constraint uq_escuela_usuario
        unique (id_escuela, id_usuario);

alter table escuela_usuario
    add constraint fk_eu_escuela
        foreign key (id_escuela) references escuela;

alter table escuela_usuario
    add constraint fk_eu_usuario
        foreign key (id_usuario) references usuario;

alter table escuela_usuario
    add constraint fk_eu_area
        foreign key (id_area) references area;

alter table escuela_usuario
    add constraint ch_escuela_usuario_rol
        check ((rol)::text = ANY ((ARRAY ['directivo'::character varying, 'jefe'::character varying])::text[]));

alter table escuela_usuario
    add constraint chk_area_rol
        check ((((rol)::text = 'jefe'::text) AND (id_area IS NOT NULL)) OR
               (((rol)::text <> 'jefe'::text) AND (id_area IS NULL)));

-- ----------------------------------------------------------------------------
-- escuela_favorita — escuelas favoritas del usuario
-- ----------------------------------------------------------------------------
create table if not exists escuela_favorita
(
    id         uuid default gen_random_uuid() not null,
    id_escuela uuid                           not null,
    id_usuario uuid                           not null
);

alter table escuela_favorita
    add constraint pk_escuela_favorita
        primary key (id);

alter table escuela_favorita
    add constraint uq_escuela_favorita
        unique (id_escuela, id_usuario);

alter table escuela_favorita
    add constraint fk_ef_escuela
        foreign key (id_escuela) references escuela
            on delete cascade;

alter table escuela_favorita
    add constraint fk_ef_usuario
        foreign key (id_usuario) references usuario
            on delete cascade;

-- ----------------------------------------------------------------------------
-- drive_link — links de Google Drive por curso dictado
-- ----------------------------------------------------------------------------
create table if not exists drive_link
(
    id               uuid default gen_random_uuid() not null,
    id_curso_escuela uuid                           not null,
    url              varchar(2048)                  not null,
    descripcion      varchar(255)
);

alter table drive_link
    add constraint pk_drive_link
        primary key (id);

alter table drive_link
    add constraint fk_drive_link_curso_escuela
        foreign key (id_curso_escuela) references curso_escuela
            on delete cascade;

alter table drive_link
    add constraint ch_drive_link_url
        check ((url)::text ~ '^https?://'::text);

-- ----------------------------------------------------------------------------
-- curso_profesor — históricos del docente en un curso dictado
-- ----------------------------------------------------------------------------
create table if not exists curso_profesor
(
    id_curso_escuela uuid               not null,
    id_profesor      uuid               not null,
    fecha_ingreso    date default now() not null,
    fecha_cese       date,
    detalles         varchar(50)
);

alter table curso_profesor
    add constraint pk_curso_profesor
        primary key (id_curso_escuela, id_profesor);

alter table curso_profesor
    add constraint fk_cp_curso_esc
        foreign key (id_curso_escuela) references curso_escuela;

alter table curso_profesor
    add constraint fk_cp_profesor
        foreign key (id_profesor) references profesor;

-- ============================================================================
-- 2) FUNCIONES AUXILIARES (policies)
-- ============================================================================

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

-- Carga rol global + escuelas del usuario a GUCs transaccionales.
-- SECURITY DEFINER: corre como owner para leer usuario/escuela_usuario
-- sin chocar con las policies de esas tablas.
-- Guard para ANÓNIMOS: sin app.user_id, carga contexto vacío en vez de
-- lanzar excepción (una policy no debe romper la query del visitante).
create or replace function fc_cargar_contexto_usuario() returns void
    security definer
    language plpgsql
as
$$
DECLARE
    v_rol     VARCHAR(20);
    v_esc_user jsonb;
    v_id_user uuid;
BEGIN
    -- Si ya fue cargado en esta transacción, no hacer nada
    IF current_setting('app.contexto_cargado', true) = 'true' THEN
        RETURN;
    END IF;

    v_id_user := fc_usuario_actual();

    -- Visitante/anónimo: contexto vacío, sin error.
    IF v_id_user IS NULL THEN
        PERFORM set_config('app.contexto_cargado', 'true', true);
        RETURN;
    END IF;

    SELECT rol
    INTO v_rol
    FROM usuario
    WHERE id = v_id_user;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'Usuario % no encontrado', v_id_user;
    END IF;

    select COALESCE(
        jsonb_agg(
            jsonb_build_object(
                'id_escuela', id_escuela,
                'rol', rol,
                'id_area', id_area
            )
        ),
        '[]'::jsonb
    )
    INTO v_esc_user
    FROM escuela_usuario
    WHERE id_usuario = fc_usuario_actual();

    -- Guardar en GUCs locales (duran solo la transacción con SET LOCAL)
    PERFORM set_config('app.rol',        v_rol,                true);
    PERFORM set_config('app.escuelas',   v_esc_user::text,     true);
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

create or replace function fc_es_directivo(p_id_escuela uuid) returns boolean
    stable
    language plpgsql
as
$$
BEGIN
    PERFORM fc_cargar_contexto_usuario();
    RETURN EXISTS (
        SELECT 1
        FROM jsonb_array_elements(
            current_setting('app.escuelas', true)::jsonb
        ) AS escuela
        WHERE escuela->>'id_escuela' = p_id_escuela::text
          AND escuela->>'rol' = 'directivo'
    );
END;
$$;

create or replace function fc_es_jefe(p_id_escuela uuid, p_id_area uuid) returns boolean
    stable
    language plpgsql
as
$$
BEGIN
    PERFORM fc_cargar_contexto_usuario();
    RETURN EXISTS (
        SELECT 1
        FROM jsonb_array_elements(
            current_setting('app.escuelas', true)::jsonb
        ) AS escuela
        WHERE escuela->>'id_escuela' = p_id_escuela::text
          AND escuela->>'id_area' = p_id_area::text
          AND escuela->>'rol' = 'jefe'
    );
END;
$$;

create or replace function fc_escuela_id_x_curso_escuela(p_id_curso_escuela uuid) returns uuid
    stable
    language sql
as
$$
    SELECT id_escuela
    FROM curso_escuela
    WHERE id = p_id_curso_escuela;
$$;

-- SECURITY DEFINER (corre como owner): evita la denegación circular de
-- intentar comprobar permisos leyendo tablas que el RLS ya está filtrando.
create or replace function fc_es_admin_directivo_jefe(p_id_curso_escuela uuid) returns boolean
    stable
    security definer
    language plpgsql
as
$$
    declare
        v_id_area uuid;
        v_id_esc uuid;
begin
    select ce.id_escuela, m.id_area
    into v_id_esc, v_id_area
    from curso_escuela ce
    join materia m on m.id = ce.id_materia
    where ce.id = p_id_curso_escuela;

    if(fc_es_admin() or fc_es_directivo(v_id_esc) or fc_es_jefe(v_id_esc, v_id_area)) then
        return true;
    end if;
    return false;
end;
$$;

-- Permiso por ESCUELA (no por curso dictado): admin global, directivo de la
-- escuela o jefe de cualquiera de sus áreas. Se usa en las policies de
-- profesor (el registro de docentes es por escuela).
create or replace function fc_es_admin_directivo_jefe_escuela(p_id_escuela uuid) returns boolean
    stable
    language plpgsql
as
$$
BEGIN
    PERFORM fc_cargar_contexto_usuario();
    IF fc_es_admin() THEN
        RETURN true;
    END IF;
    RETURN EXISTS (
        SELECT 1
        FROM jsonb_array_elements(
            current_setting('app.escuelas', true)::jsonb
        ) AS escuela
        WHERE escuela->>'id_escuela' = p_id_escuela::text
          AND escuela->>'rol' IN ('directivo', 'jefe')
    );
END;
$$;

create or replace function fc_es_directivo_general() returns boolean
    stable
    language plpgsql
as
$$
BEGIN
    PERFORM fc_cargar_contexto_usuario();
    RETURN EXISTS (
        SELECT 1
        FROM jsonb_array_elements(
            current_setting('app.escuelas', true)::jsonb
        ) AS escuela
        WHERE escuela->>'rol' = 'directivo'
    );
END;
$$;

-- SECURITY DEFINER (corre como owner): mismo criterio que
-- fc_es_admin_directivo_jefe, evita la denegación circular con RLS activo.
create or replace function fc_es_jefe_x_curso_escuela(p_id_curso_escuela uuid) returns boolean
    stable
    security definer
    language plpgsql
as
$$
    declare
        v_id_area uuid;
        v_id_esc uuid;
begin
    select ce.id_escuela, m.id_area
    into v_id_esc, v_id_area
    from curso_escuela ce
    join materia m on m.id = ce.id_materia
    where ce.id = p_id_curso_escuela;

    return fc_es_jefe(v_id_esc, v_id_area);
    end;
$$;

-- ----------------------------------------------------------------------------
-- fc_autenticar(p_email): lectura de credenciales del camino ANÓNIMO
-- (login y registro). Ver "Modelo de seguridad" en el encabezado.
-- ----------------------------------------------------------------------------
-- CORRECCIÓN (P0): pc_usuario_select exige admin/self/directivo, un contexto
-- que sólo existe DESPUÉS de autenticar. Sin esta función el visitante anónimo
-- no podía leer la fila del usuario (huevo y gallina): findByEmail() devolvía
-- NULL siempre, POST /login rechazaba credenciales válidas y POST /registro
-- fallaba al releer la fila recién insertada.
-- SECURITY DEFINER: corre como owner (que bypasea RLS) y expone SÓLO las
-- columnas que la autenticación necesita, para un email dado. No ensancha
-- ninguna policy: el SELECT anónimo directo sobre usuario sigue denegado.
-- search_path fijo: patrón recomendado para funciones DEFINER (evita que un
-- objeto del mismo nombre en otro schema se resuelva dentro de la función).
create or replace function fc_autenticar(p_email text)
    returns table (id uuid, nombre varchar, email varchar, pass varchar, rol varchar)
    stable
    security definer
    set search_path = public, pg_temp
    language sql
as
$$
    SELECT u.id, u.nombre, u.email, u.pass, u.rol
    FROM usuario u
    WHERE lower(u.email) = lower(p_email)
    LIMIT 1;
$$;

-- ----------------------------------------------------------------------------
-- fc_curso_profesor_activo(p_id_curso_escuela, p_id_profesor): lectura del
-- camino PÚBLICO para el filtro por profesor de la navegación (PR3).
-- ----------------------------------------------------------------------------
-- pc_curso_profesor_select exige admin/directivo/jefe del curso: un visitante
-- que filtra cursos por profesor no veía ninguna asignación (falso "No hay
-- cursos que coincidan con los filtros"). SECURITY DEFINER: corre como owner
-- (bypasea RLS) y expone SÓLO un booleano para el par (curso, profesor) dado:
-- no devuelve filas de curso_profesor y no ensancha ninguna policy (el SELECT
-- anónimo directo sobre curso_profesor sigue denegado).
-- search_path fijo: patrón recomendado para funciones DEFINER (ver fc_autenticar).
create or replace function fc_curso_profesor_activo(p_id_curso_escuela uuid, p_id_profesor uuid)
    returns boolean
    stable
    security definer
    set search_path = public, pg_temp
    language sql
as
$$
    SELECT EXISTS (
        SELECT 1
        FROM curso_profesor cp
        WHERE cp.id_curso_escuela = p_id_curso_escuela
          AND cp.id_profesor = p_id_profesor
          AND cp.fecha_cese IS NULL
    );
$$;

-- ============================================================================
-- 3) POLICIES
-- ============================================================================

-- ----------------------------------------------------------------------------
-- usuario
-- ----------------------------------------------------------------------------
create policy pc_usuario_delete on usuario
    as permissive
    for delete
    using (fc_es_admin() OR (id = fc_usuario_actual()));

-- UPDATE: el usuario puede editarse a sí mismo PERO sin auto-promoverse a
-- admin (rol queda fijo 'user' salvo que lo cambie un admin global).
create policy pc_usuario_update on usuario
    as permissive
    for update
    using (fc_es_admin() OR (fc_usuario_actual() = id))
    with check (fc_es_admin() OR (fc_usuario_actual() = id AND rol = 'user'));

-- Registro público: la app solo crea usuarios rol='user' (AuthController).
create policy pc_usuario_insert on usuario
    as permissive
    for insert
    with check (rol = 'user');

-- Lectura: admin global, el propio usuario, o directivo de alguna escuela
-- (necesario para asignar roles por email en EscuelaUsuarioController).
create policy pc_usuario_select on usuario
    as permissive
    for select
    using (fc_es_admin() OR (fc_usuario_actual() = id) OR fc_es_directivo_general());

-- ----------------------------------------------------------------------------
-- curso
-- ----------------------------------------------------------------------------
create policy curso_update on curso
    as permissive
    for update
    using (fc_es_admin())
with check (fc_es_admin());

create policy curso_delete on curso
    as permissive
    for delete
    using (fc_es_admin());

create policy pc_curso_insert on curso
    as permissive
    for insert
    with check (fc_es_admin() OR fc_es_directivo_general());

-- Catálogo compartido: visible para todos (público).
create policy pc_curso_select on curso
    as permissive
    for select
    using (true);

-- ----------------------------------------------------------------------------
-- profesor
-- ----------------------------------------------------------------------------
-- CORRECCIÓN: el helper recibe id_ESCUELA (no id_curso_escuela). Usa
-- fc_es_admin_directivo_jefe_escuela() para que admin, directivo y jefe de
-- la escuela puedan gestionar el registro de docentes.
create policy pc_profesor_delete on profesor
    as permissive
    for delete
    using (fc_es_admin_directivo_jefe_escuela(id_escuela));

create policy pc_profesor_update on profesor
    as permissive
    for update
    using (fc_es_admin_directivo_jefe_escuela(id_escuela))
with check (fc_es_admin_directivo_jefe_escuela(id_escuela));

create policy pc_profesor_insert on profesor
    as permissive
    for insert
    with check (fc_es_admin_directivo_jefe_escuela(id_escuela));

create policy pc_profesor_select on profesor
    as permissive
    for select
    using (fc_es_admin_directivo_jefe_escuela(id_escuela));

-- ----------------------------------------------------------------------------
-- materia
-- ----------------------------------------------------------------------------
create policy pc_materia_delete on materia
    as permissive
    for delete
    using (fc_es_admin());

create policy pc_materia_update on materia
    as permissive
    for update
    using (fc_es_admin())
with check (fc_es_admin());

create policy pc_materia_insert on materia
    as permissive
    for insert
    with check (fc_es_admin() OR fc_es_directivo_general());

-- Catálogo compartido: visible para todos (público).
create policy pc_materia_select on materia
    as permissive
    for select
    using (true);

-- ----------------------------------------------------------------------------
-- area
-- ----------------------------------------------------------------------------
create policy pc_area_delete on area
    as permissive
    for delete
    using (fc_es_admin());

create policy pc_area_update on area
    as permissive
    for update
    using (fc_es_admin())
with check (fc_es_admin());

create policy pc_area_insert on area
    as permissive
    for insert
    with check (fc_es_admin() OR fc_es_directivo_general());

-- Catálogo compartido: visible para todos (público).
create policy pc_area_select on area
    as permissive
    for select
    using (true);

-- ----------------------------------------------------------------------------
-- escuela
-- ----------------------------------------------------------------------------
create policy pc_escuela_delete on escuela
    as permissive
    for delete
    using (fc_es_admin());

create policy pc_escuela_update on escuela
    as permissive
    for update
    using (fc_es_admin())
with check (fc_es_admin());

create policy pc_escuela_insert on escuela
    as permissive
    for insert
    with check (fc_es_admin());

-- Lectura pública: la home (PR3) muestra las escuelas a visitantes.
create policy pc_escuela_select on escuela
    as permissive
    for select
    using (true);

-- ----------------------------------------------------------------------------
-- curso_escuela
-- ----------------------------------------------------------------------------
create policy pc_curso_escuela_insert on curso_escuela
    as permissive
    for insert
    with check (fc_es_admin() OR fc_es_directivo(id_escuela));

create policy pc_curso_escuela_update on curso_escuela
    as permissive
    for update
    using (fc_es_admin() OR fc_es_directivo(id_escuela))
    with check (fc_es_admin() OR fc_es_directivo(id_escuela));

create policy pc_curso_escuela_delete on curso_escuela
    as permissive
    for delete
    using (fc_es_admin() OR fc_es_directivo(id_escuela));

-- Lectura pública: detalle de cursos visible en la navegación (PR3).
create policy pc_curso_escuela_select on curso_escuela
    as permissive
    for select
    using (true);

-- ----------------------------------------------------------------------------
-- dia
-- ----------------------------------------------------------------------------
create policy pc_dia_update on dia
    as permissive
    for update
    using (fc_es_admin())
with check (fc_es_admin());

create policy pc_dia_delete on dia
    as permissive
    for delete
    using (fc_es_admin());

create policy pc_dia_insert on dia
    as permissive
    for insert
    with check (fc_es_admin());

-- Catálogo compartido: visible para todos (público).
create policy pc_dia_select on dia
    as permissive
    for select
    using (true);

-- ----------------------------------------------------------------------------
-- curso_escuela_dia
-- ----------------------------------------------------------------------------
create policy pc_ced_insert on curso_escuela_dia
    as permissive
    for insert
    with check (fc_es_admin() OR fc_es_directivo(fc_escuela_id_x_curso_escuela(id_curso_escuela)));

create policy pc_ced_update on curso_escuela_dia
    as permissive
    for update
    using (fc_es_admin() OR fc_es_directivo(fc_escuela_id_x_curso_escuela(id_curso_escuela)))
    with check (fc_es_admin() OR fc_es_directivo(fc_escuela_id_x_curso_escuela(id_curso_escuela)));

create policy pc_ced_delete on curso_escuela_dia
    as permissive
    for delete
    using (fc_es_admin() OR fc_es_directivo(fc_escuela_id_x_curso_escuela(id_curso_escuela)));

-- Lectura pública: horarios visibles en la navegación (PR3).
create policy pc_ced_select on curso_escuela_dia
    as permissive
    for select
    using (true);

-- ----------------------------------------------------------------------------
-- orientacion
-- ----------------------------------------------------------------------------
create policy pc_orientacion_delete on orientacion
    as permissive
    for delete
    using (fc_es_admin());

create policy pc_orientacion_update on orientacion
    as permissive
    for update
    using (fc_es_admin())
with check (fc_es_admin());

create policy pc_orientacion_insert on orientacion
    as permissive
    for insert
    with check (fc_es_admin() OR fc_es_directivo_general());

-- Catálogo compartido: visible para todos (público).
create policy pc_orientacion_select on orientacion
    as permissive
    for select
    using (true);

-- ----------------------------------------------------------------------------
-- turno
-- ----------------------------------------------------------------------------
create policy pc_turno_update on turno
    as permissive
    for update
    using (fc_es_admin())
with check (fc_es_admin());

create policy pc_turno_delete on turno
    as permissive
    for delete
    using (fc_es_admin());

create policy pc_turno_insert on turno
    as permissive
    for insert
    with check (fc_es_admin());

-- Catálogo compartido: visible para todos (público).
create policy pc_turno_select on turno
    as permissive
    for select
    using (true);

-- ----------------------------------------------------------------------------
-- escuela_turno
-- ----------------------------------------------------------------------------
create policy pc_esc_turno_delete on escuela_turno
    as permissive
    for delete
    using (fc_es_admin() OR fc_es_directivo(id_escuela));

create policy pc_esc_turno_update on escuela_turno
    as permissive
    for update
    using (fc_es_admin() OR fc_es_directivo(id_escuela))
    with check (fc_es_admin() OR fc_es_directivo(id_escuela));

create policy pc_esc_turno_insert on escuela_turno
    as permissive
    for insert
    with check (fc_es_admin() OR fc_es_directivo(id_escuela));

-- Lectura pública: turnos de la escuela visibles en la navegación (PR3).
create policy pc_esc_turno_select on escuela_turno
    as permissive
    for select
    using (true);

-- ----------------------------------------------------------------------------
-- escuela_orientacion
-- ----------------------------------------------------------------------------
create policy pc_esc_or_delete on escuela_orientacion
    as permissive
    for delete
    using (fc_es_admin() OR fc_es_directivo(id_escuela));

create policy pc_esc_or_update on escuela_orientacion
    as permissive
    for update
    using (fc_es_admin() OR fc_es_directivo(id_escuela))
    with check (fc_es_admin() OR fc_es_directivo(id_escuela));

create policy pc_esc_or_insert on escuela_orientacion
    as permissive
    for insert
    with check (fc_es_admin() OR fc_es_directivo(id_escuela));

-- Lectura pública: orientaciones de la escuela visibles en la navegación (PR3).
create policy pc_esc_or_select on escuela_orientacion
    as permissive
    for select
    using (true);

-- ----------------------------------------------------------------------------
-- tarea
-- ----------------------------------------------------------------------------
create policy pc_tarea_delete on tarea
    as permissive
    for delete
    using (fc_es_admin() OR fc_es_jefe_x_curso_escuela(id_curso_escuela));

create policy pc_tarea_update on tarea
    as permissive
    for update
    using (fc_es_admin() OR fc_es_jefe_x_curso_escuela(id_curso_escuela))
    with check (fc_es_admin() OR fc_es_jefe_x_curso_escuela(id_curso_escuela));

create policy pc_tarea_insert on tarea
    as permissive
    for insert
    with check (fc_es_admin() OR fc_es_jefe_x_curso_escuela(id_curso_escuela));

create policy pc_tarea_select on tarea
    as permissive
    for select
    using (fc_es_admin_directivo_jefe(id_curso_escuela));

-- ----------------------------------------------------------------------------
-- escuela_usuario
-- ----------------------------------------------------------------------------
create policy pc_esc_usuario_delete on escuela_usuario
    as permissive
    for delete
    using (fc_es_admin() OR fc_es_directivo(id_escuela));

create policy pc_esc_usuario_update on escuela_usuario
    as permissive
    for update
    using (fc_es_admin() OR fc_es_directivo(id_escuela))
    with check (fc_es_admin() OR fc_es_directivo(id_escuela));

create policy pc_esc_usuario_insert on escuela_usuario
    as permissive
    for insert
    with check (fc_es_admin() OR fc_es_directivo(id_escuela));

create policy pc_esc_usuario_select on escuela_usuario
    as permissive
    for select
    using (fc_es_admin() OR (fc_usuario_actual() = id_usuario) OR fc_es_directivo(id_escuela));

-- ----------------------------------------------------------------------------
-- escuela_favorita
-- ----------------------------------------------------------------------------
create policy pc_esc_fav_delete on escuela_favorita
    as permissive
    for delete
    using (fc_usuario_actual() = id_usuario);

create policy pc_esc_fav_insert on escuela_favorita
    as permissive
    for insert
    with check (fc_usuario_actual() = id_usuario);

create policy pc_esc_fav_select on escuela_favorita
    as permissive
    for select
    using (fc_usuario_actual() = id_usuario);

create policy pc_esc_fav_update on escuela_favorita
    as permissive
    for update
    using (fc_usuario_actual() = id_usuario)
    with check (fc_usuario_actual() = id_usuario);

-- ----------------------------------------------------------------------------
-- drive_link
-- ----------------------------------------------------------------------------
create policy pc_drive_insert on drive_link
    as permissive
    for insert
    with check (fc_es_admin_directivo_jefe(id_curso_escuela));

create policy pc_drive_update on drive_link
    as permissive
    for update
    using (fc_es_admin_directivo_jefe(id_curso_escuela))
with check (fc_es_admin_directivo_jefe(id_curso_escuela));

create policy pc_drive_delete on drive_link
    as permissive
    for delete
    using (fc_es_admin_directivo_jefe(id_curso_escuela));

-- Lectura pública: material compartido. Si los links deben ser internos
-- (solo staff), cambiar a fc_es_admin_directivo_jefe(id_curso_escuela).
create policy pc_drive_select on drive_link
    as permissive
    for select
    using (true);

-- ----------------------------------------------------------------------------
-- curso_profesor
-- ----------------------------------------------------------------------------
create policy pc_curso_profesor_delete on curso_profesor
    as permissive
    for delete
    using (fc_es_admin_directivo_jefe(id_curso_escuela));

create policy pc_curso_profesor_update on curso_profesor
    as permissive
    for update
    using (fc_es_admin_directivo_jefe(id_curso_escuela))
with check (fc_es_admin_directivo_jefe(id_curso_escuela));

create policy pc_curso_profesor_insert on curso_profesor
    as permissive
    for insert
    with check (fc_es_admin_directivo_jefe(id_curso_escuela));

create policy pc_curso_profesor_select on curso_profesor
    as permissive
    for select
    using (fc_es_admin_directivo_jefe(id_curso_escuela));

-- ============================================================================
-- 4) RLS: activar en todas las tablas
-- ============================================================================
ALTER TABLE usuario ENABLE ROW LEVEL SECURITY;
ALTER TABLE curso ENABLE ROW LEVEL SECURITY;
ALTER TABLE profesor ENABLE ROW LEVEL SECURITY;
ALTER TABLE materia ENABLE ROW LEVEL SECURITY;
ALTER TABLE area ENABLE ROW LEVEL SECURITY;
ALTER TABLE escuela ENABLE ROW LEVEL SECURITY;
ALTER TABLE curso_escuela ENABLE ROW LEVEL SECURITY;
ALTER TABLE dia ENABLE ROW LEVEL SECURITY;
ALTER TABLE curso_escuela_dia ENABLE ROW LEVEL SECURITY;
ALTER TABLE orientacion ENABLE ROW LEVEL SECURITY;
ALTER TABLE turno ENABLE ROW LEVEL SECURITY;
ALTER TABLE escuela_turno ENABLE ROW LEVEL SECURITY;
ALTER TABLE escuela_orientacion ENABLE ROW LEVEL SECURITY;
ALTER TABLE tarea ENABLE ROW LEVEL SECURITY;
ALTER TABLE escuela_usuario ENABLE ROW LEVEL SECURITY;
ALTER TABLE escuela_favorita ENABLE ROW LEVEL SECURITY;
ALTER TABLE drive_link ENABLE ROW LEVEL SECURITY;
ALTER TABLE curso_profesor ENABLE ROW LEVEL SECURITY;

-- ============================================================================
-- 5) PRIVILEGIOS para app_role
-- (UNICO cliente de la app; NO es owner, por eso RLS lo afecta).
-- Se aplican como superusuario/owner en la misma corrida.
-- ============================================================================
GRANT USAGE ON SCHEMA public TO app_role;
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO app_role;
GRANT EXECUTE ON ALL FUNCTIONS IN SCHEMA public TO app_role;

-- fc_autenticar devuelve el hash de un email dado: se revoca de PUBLIC para
-- que no quede al alcance de roles ajenos a la app (el owner la conserva).
REVOKE ALL ON FUNCTION fc_autenticar(text) FROM PUBLIC;
GRANT EXECUTE ON FUNCTION fc_autenticar(text) TO app_role;

-- fc_curso_profesor_activo: mismo criterio (solo la app la ejecuta; la
-- ejecuta tanto el visitante como los roles autenticados porque la conexión
-- de la app siempre corre como app_role).
REVOKE ALL ON FUNCTION fc_curso_profesor_activo(uuid, uuid) FROM PUBLIC;
GRANT EXECUTE ON FUNCTION fc_curso_profesor_activo(uuid, uuid) TO app_role;

-- Objetos futuros creados por el owner (ej. tests de verificación).
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO app_role;