--
-- PostgreSQL database dump
--

\restrict bZmKeIY2UAPaFMREOxQJrcY0kxkzsAVbKgL4j71VPrxYR7DHD4SFXjB9Bah0WMk

-- Dumped from database version 17.11
-- Dumped by pg_dump version 17.11

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: pg_trgm; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS pg_trgm WITH SCHEMA public;


--
-- Name: EXTENSION pg_trgm; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION pg_trgm IS 'text similarity measurement and index searching based on trigrams';


SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: attachments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.attachments (
    id bigint NOT NULL,
    filename character varying(255) NOT NULL,
    original_filename character varying(255) NOT NULL,
    file_path character varying(255) NOT NULL,
    mime_type character varying(255) NOT NULL,
    file_size integer NOT NULL,
    attachable_type character varying(255) NOT NULL,
    attachable_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: attachments_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.attachments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: attachments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.attachments_id_seq OWNED BY public.attachments.id;


--
-- Name: cache; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration integer NOT NULL
);


--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration integer NOT NULL
);


--
-- Name: canned_responses; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.canned_responses (
    id bigint NOT NULL,
    created_by_user_id bigint NOT NULL,
    title character varying(255) NOT NULL,
    body text NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: canned_responses_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.canned_responses_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: canned_responses_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.canned_responses_id_seq OWNED BY public.canned_responses.id;


--
-- Name: categories; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.categories (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    description character varying(255),
    color character varying(7) DEFAULT '#3B82F6'::character varying NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: categories_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.categories_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: categories_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.categories_id_seq OWNED BY public.categories.id;


--
-- Name: credential_handoffs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.credential_handoffs (
    id bigint NOT NULL,
    target_user_id bigint NOT NULL,
    issued_by_user_id bigint,
    temporary_password text NOT NULL,
    expires_at timestamp(0) without time zone NOT NULL,
    revealed_at timestamp(0) without time zone,
    consumed_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: credential_handoffs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.credential_handoffs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: credential_handoffs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.credential_handoffs_id_seq OWNED BY public.credential_handoffs.id;


--
-- Name: departments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.departments (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    logo_path character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: departments_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.departments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: departments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.departments_id_seq OWNED BY public.departments.id;


--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection text NOT NULL,
    queue text NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;


--
-- Name: job_batches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.job_batches (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);


--
-- Name: jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sessions (
    id character varying(255) NOT NULL,
    user_id bigint,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);


--
-- Name: system_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.system_logs (
    id bigint NOT NULL,
    actor_user_id bigint,
    category character varying(64) NOT NULL,
    event_type character varying(120) NOT NULL,
    target_type character varying(255),
    target_id bigint,
    description text NOT NULL,
    metadata json,
    ip_address character varying(45),
    user_agent character varying(255),
    occurred_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: system_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.system_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: system_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.system_logs_id_seq OWNED BY public.system_logs.id;


--
-- Name: ticket_assignment_rules; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ticket_assignment_rules (
    id bigint NOT NULL,
    category_id bigint NOT NULL,
    user_id bigint NOT NULL,
    weight smallint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: ticket_assignment_rules_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ticket_assignment_rules_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ticket_assignment_rules_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ticket_assignment_rules_id_seq OWNED BY public.ticket_assignment_rules.id;


--
-- Name: ticket_assignment_settings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ticket_assignment_settings (
    id bigint NOT NULL,
    distribution_method character varying(30) DEFAULT 'weighted_random'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: ticket_assignment_settings_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ticket_assignment_settings_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ticket_assignment_settings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ticket_assignment_settings_id_seq OWNED BY public.ticket_assignment_settings.id;


--
-- Name: ticket_assignments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ticket_assignments (
    id bigint NOT NULL,
    ticket_id bigint NOT NULL,
    user_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: ticket_assignments_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ticket_assignments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ticket_assignments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ticket_assignments_id_seq OWNED BY public.ticket_assignments.id;


--
-- Name: ticket_replies; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ticket_replies (
    id bigint NOT NULL,
    ticket_id bigint NOT NULL,
    user_id bigint NOT NULL,
    message text NOT NULL,
    is_internal boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    reply_to_id bigint,
    edited_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    client_notified_reply_at timestamp(0) without time zone
);


--
-- Name: ticket_replies_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ticket_replies_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ticket_replies_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ticket_replies_id_seq OWNED BY public.ticket_replies.id;


--
-- Name: ticket_saved_views; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ticket_saved_views (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    name character varying(80) NOT NULL,
    name_normalized character varying(80) NOT NULL,
    filters json NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: ticket_saved_views_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ticket_saved_views_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ticket_saved_views_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ticket_saved_views_id_seq OWNED BY public.ticket_saved_views.id;


--
-- Name: ticket_user_states; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ticket_user_states (
    id bigint NOT NULL,
    ticket_id bigint NOT NULL,
    user_id bigint NOT NULL,
    last_seen_at timestamp(0) without time zone,
    dismissed_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    acknowledged_at timestamp(0) without time zone
);


--
-- Name: ticket_user_states_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ticket_user_states_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ticket_user_states_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ticket_user_states_id_seq OWNED BY public.ticket_user_states.id;


--
-- Name: tickets; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tickets (
    id bigint NOT NULL,
    ticket_number character varying(255) NOT NULL,
    subject character varying(255) NOT NULL,
    description text NOT NULL,
    priority character varying(255),
    status character varying(255) DEFAULT 'open'::character varying NOT NULL,
    user_id bigint NOT NULL,
    assigned_to bigint,
    category_id bigint NOT NULL,
    resolved_at timestamp(0) without time zone,
    closed_at timestamp(0) without time zone,
    satisfaction_rating integer,
    satisfaction_comment text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    name character varying(255),
    contact_number character varying(30),
    email character varying(255),
    province character varying(120),
    municipality character varying(120),
    assigned_at timestamp(0) without time zone,
    technical_user_notified_assignment_at timestamp(0) without time zone,
    technical_user_notified_sla_at timestamp(0) without time zone,
    consent_accepted_at timestamp(0) without time zone,
    consent_version character varying(50),
    consent_ip_address character varying(45),
    consent_user_agent text,
    closed_by bigint,
    is_imported boolean DEFAULT false NOT NULL,
    ticket_type character varying(16) DEFAULT 'external'::character varying NOT NULL,
    created_by_user_id bigint,
    creation_source character varying(32),
    super_users_notified_new_at timestamp(0) without time zone,
    super_users_notified_unchecked_at timestamp(0) without time zone,
    super_users_notified_unassigned_sla_at timestamp(0) without time zone,
    CONSTRAINT tickets_priority_check CHECK (((priority IS NULL) OR ((priority)::text = ANY (ARRAY[('severity_1'::character varying)::text, ('severity_2'::character varying)::text, ('severity_3'::character varying)::text])))),
    CONSTRAINT tickets_status_check CHECK (((status)::text = ANY (ARRAY[('open'::character varying)::text, ('in_progress'::character varying)::text, ('pending'::character varying)::text, ('resolved'::character varying)::text, ('closed'::character varying)::text])))
);


--
-- Name: tickets_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.tickets_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tickets_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.tickets_id_seq OWNED BY public.tickets.id;


--
-- Name: user_legal_consents; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.user_legal_consents (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    terms_version character varying(50) NOT NULL,
    privacy_version character varying(50) NOT NULL,
    platform_consent_version character varying(50) NOT NULL,
    accepted_at timestamp(0) without time zone NOT NULL,
    ip_address character varying(45),
    user_agent text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: user_legal_consents_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.user_legal_consents_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: user_legal_consents_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.user_legal_consents_id_seq OWNED BY public.user_legal_consents.id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.users (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    email character varying(255),
    phone character varying(255),
    department character varying(255),
    role character varying(255) DEFAULT 'client'::character varying NOT NULL,
    email_verified_at timestamp(0) without time zone,
    password character varying(255) NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    is_profile_locked boolean DEFAULT false NOT NULL,
    client_notes text,
    must_change_password boolean DEFAULT false NOT NULL,
    username character varying(255),
    CONSTRAINT users_role_check CHECK (((role)::text = ANY (ARRAY['client'::text, 'shadow'::text, 'admin'::text, 'super_user'::text, 'technical'::text])))
);


--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: attachments id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.attachments ALTER COLUMN id SET DEFAULT nextval('public.attachments_id_seq'::regclass);


--
-- Name: canned_responses id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.canned_responses ALTER COLUMN id SET DEFAULT nextval('public.canned_responses_id_seq'::regclass);


--
-- Name: categories id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categories ALTER COLUMN id SET DEFAULT nextval('public.categories_id_seq'::regclass);


--
-- Name: credential_handoffs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.credential_handoffs ALTER COLUMN id SET DEFAULT nextval('public.credential_handoffs_id_seq'::regclass);


--
-- Name: departments id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.departments ALTER COLUMN id SET DEFAULT nextval('public.departments_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: system_logs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.system_logs ALTER COLUMN id SET DEFAULT nextval('public.system_logs_id_seq'::regclass);


--
-- Name: ticket_assignment_rules id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_assignment_rules ALTER COLUMN id SET DEFAULT nextval('public.ticket_assignment_rules_id_seq'::regclass);


--
-- Name: ticket_assignment_settings id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_assignment_settings ALTER COLUMN id SET DEFAULT nextval('public.ticket_assignment_settings_id_seq'::regclass);


--
-- Name: ticket_assignments id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_assignments ALTER COLUMN id SET DEFAULT nextval('public.ticket_assignments_id_seq'::regclass);


--
-- Name: ticket_replies id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_replies ALTER COLUMN id SET DEFAULT nextval('public.ticket_replies_id_seq'::regclass);


--
-- Name: ticket_saved_views id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_saved_views ALTER COLUMN id SET DEFAULT nextval('public.ticket_saved_views_id_seq'::regclass);


--
-- Name: ticket_user_states id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_user_states ALTER COLUMN id SET DEFAULT nextval('public.ticket_user_states_id_seq'::regclass);


--
-- Name: tickets id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tickets ALTER COLUMN id SET DEFAULT nextval('public.tickets_id_seq'::regclass);


--
-- Name: user_legal_consents id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_legal_consents ALTER COLUMN id SET DEFAULT nextval('public.user_legal_consents_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Name: attachments attachments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.attachments
    ADD CONSTRAINT attachments_pkey PRIMARY KEY (id);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: canned_responses canned_responses_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.canned_responses
    ADD CONSTRAINT canned_responses_pkey PRIMARY KEY (id);


--
-- Name: categories categories_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categories
    ADD CONSTRAINT categories_pkey PRIMARY KEY (id);


--
-- Name: credential_handoffs credential_handoffs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.credential_handoffs
    ADD CONSTRAINT credential_handoffs_pkey PRIMARY KEY (id);


--
-- Name: credential_handoffs credential_handoffs_target_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.credential_handoffs
    ADD CONSTRAINT credential_handoffs_target_user_id_unique UNIQUE (target_user_id);


--
-- Name: departments departments_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.departments
    ADD CONSTRAINT departments_name_unique UNIQUE (name);


--
-- Name: departments departments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.departments
    ADD CONSTRAINT departments_pkey PRIMARY KEY (id);


--
-- Name: departments departments_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.departments
    ADD CONSTRAINT departments_slug_unique UNIQUE (slug);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: system_logs system_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.system_logs
    ADD CONSTRAINT system_logs_pkey PRIMARY KEY (id);


--
-- Name: ticket_assignment_rules ticket_assignment_rules_category_id_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_assignment_rules
    ADD CONSTRAINT ticket_assignment_rules_category_id_user_id_unique UNIQUE (category_id, user_id);


--
-- Name: ticket_assignment_rules ticket_assignment_rules_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_assignment_rules
    ADD CONSTRAINT ticket_assignment_rules_pkey PRIMARY KEY (id);


--
-- Name: ticket_assignment_settings ticket_assignment_settings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_assignment_settings
    ADD CONSTRAINT ticket_assignment_settings_pkey PRIMARY KEY (id);


--
-- Name: ticket_assignments ticket_assignments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_assignments
    ADD CONSTRAINT ticket_assignments_pkey PRIMARY KEY (id);


--
-- Name: ticket_assignments ticket_assignments_ticket_id_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_assignments
    ADD CONSTRAINT ticket_assignments_ticket_id_user_id_unique UNIQUE (ticket_id, user_id);


--
-- Name: ticket_replies ticket_replies_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_replies
    ADD CONSTRAINT ticket_replies_pkey PRIMARY KEY (id);


--
-- Name: ticket_saved_views ticket_saved_views_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_saved_views
    ADD CONSTRAINT ticket_saved_views_pkey PRIMARY KEY (id);


--
-- Name: ticket_saved_views ticket_saved_views_user_normalized_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_saved_views
    ADD CONSTRAINT ticket_saved_views_user_normalized_name_unique UNIQUE (user_id, name_normalized);


--
-- Name: ticket_user_states ticket_user_states_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_user_states
    ADD CONSTRAINT ticket_user_states_pkey PRIMARY KEY (id);


--
-- Name: ticket_user_states ticket_user_states_ticket_id_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_user_states
    ADD CONSTRAINT ticket_user_states_ticket_id_user_id_unique UNIQUE (ticket_id, user_id);


--
-- Name: tickets tickets_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tickets
    ADD CONSTRAINT tickets_pkey PRIMARY KEY (id);


--
-- Name: tickets tickets_ticket_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tickets
    ADD CONSTRAINT tickets_ticket_number_unique UNIQUE (ticket_number);


--
-- Name: user_legal_consents user_legal_consents_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_legal_consents
    ADD CONSTRAINT user_legal_consents_pkey PRIMARY KEY (id);


--
-- Name: user_legal_consents user_legal_consents_version_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_legal_consents
    ADD CONSTRAINT user_legal_consents_version_unique UNIQUE (user_id, terms_version, privacy_version, platform_consent_version);


--
-- Name: users users_email_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_unique UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: users users_username_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_username_unique UNIQUE (username);


--
-- Name: attachments_attachable_type_attachable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX attachments_attachable_type_attachable_id_index ON public.attachments USING btree (attachable_type, attachable_id);


--
-- Name: canned_responses_title_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX canned_responses_title_index ON public.canned_responses USING btree (title);


--
-- Name: categories_name_trgm_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX categories_name_trgm_idx ON public.categories USING gin (lower((name)::text) public.gin_trgm_ops);


--
-- Name: credential_handoffs_expiry_consumed_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX credential_handoffs_expiry_consumed_idx ON public.credential_handoffs USING btree (expires_at, consumed_at);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: replies_client_notify_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX replies_client_notify_idx ON public.ticket_replies USING btree (ticket_id, is_internal, client_notified_reply_at);


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- Name: system_logs_category_occurred_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX system_logs_category_occurred_at_index ON public.system_logs USING btree (category, occurred_at);


--
-- Name: system_logs_event_type_occurred_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX system_logs_event_type_occurred_at_index ON public.system_logs USING btree (event_type, occurred_at);


--
-- Name: system_logs_target_type_target_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX system_logs_target_type_target_id_index ON public.system_logs USING btree (target_type, target_id);


--
-- Name: ticket_assignments_user_id_ticket_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ticket_assignments_user_id_ticket_id_index ON public.ticket_assignments USING btree (user_id, ticket_id);


--
-- Name: ticket_replies_ticket_internal_created_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ticket_replies_ticket_internal_created_idx ON public.ticket_replies USING btree (ticket_id, is_internal, created_at);


--
-- Name: ticket_replies_ticket_updated_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ticket_replies_ticket_updated_idx ON public.ticket_replies USING btree (ticket_id, updated_at);


--
-- Name: ticket_replies_ticket_user_created_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ticket_replies_ticket_user_created_idx ON public.ticket_replies USING btree (ticket_id, user_id, created_at);


--
-- Name: ticket_saved_views_user_id_updated_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ticket_saved_views_user_id_updated_at_index ON public.ticket_saved_views USING btree (user_id, updated_at);


--
-- Name: ticket_user_states_user_id_acknowledged_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ticket_user_states_user_id_acknowledged_at_index ON public.ticket_user_states USING btree (user_id, acknowledged_at);


--
-- Name: ticket_user_states_user_id_dismissed_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ticket_user_states_user_id_dismissed_at_index ON public.ticket_user_states USING btree (user_id, dismissed_at);


--
-- Name: ticket_user_states_user_id_last_seen_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ticket_user_states_user_id_last_seen_at_index ON public.ticket_user_states USING btree (user_id, last_seen_at);


--
-- Name: tickets_assigned_at_technical_user_notified_sla_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tickets_assigned_at_technical_user_notified_sla_at_index ON public.tickets USING btree (assigned_at, technical_user_notified_sla_at);


--
-- Name: tickets_assigned_status_updated_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tickets_assigned_status_updated_idx ON public.tickets USING btree (assigned_to, status, updated_at);


--
-- Name: tickets_category_status_created_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tickets_category_status_created_idx ON public.tickets USING btree (category_id, status, created_at);


--
-- Name: tickets_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tickets_created_at_index ON public.tickets USING btree (created_at);


--
-- Name: tickets_created_status_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tickets_created_status_idx ON public.tickets USING btree (created_at, status);


--
-- Name: tickets_creation_source_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tickets_creation_source_index ON public.tickets USING btree (creation_source);


--
-- Name: tickets_creator_created_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tickets_creator_created_idx ON public.tickets USING btree (created_by_user_id, created_at);


--
-- Name: tickets_municipality_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tickets_municipality_idx ON public.tickets USING btree (municipality);


--
-- Name: tickets_municipality_lower_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tickets_municipality_lower_idx ON public.tickets USING btree (lower((municipality)::text));


--
-- Name: tickets_priority_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tickets_priority_index ON public.tickets USING btree (priority);


--
-- Name: tickets_priority_status_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tickets_priority_status_idx ON public.tickets USING btree (priority, status);


--
-- Name: tickets_province_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tickets_province_idx ON public.tickets USING btree (province);


--
-- Name: tickets_province_lower_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tickets_province_lower_idx ON public.tickets USING btree (lower((province)::text));


--
-- Name: tickets_status_assigned_to_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tickets_status_assigned_to_index ON public.tickets USING btree (status, assigned_to);


--
-- Name: tickets_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tickets_status_index ON public.tickets USING btree (status);


--
-- Name: tickets_status_priority_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tickets_status_priority_created_at_index ON public.tickets USING btree (status, priority, created_at);


--
-- Name: tickets_status_super_new_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tickets_status_super_new_idx ON public.tickets USING btree (status, super_users_notified_new_at);


--
-- Name: tickets_status_super_unchecked_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tickets_status_super_unchecked_idx ON public.tickets USING btree (status, super_users_notified_unchecked_at, created_at);


--
-- Name: tickets_status_support_sla_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tickets_status_support_sla_idx ON public.tickets USING btree (status, technical_user_notified_sla_at, assigned_at);


--
-- Name: tickets_status_unassigned_super_sla_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tickets_status_unassigned_super_sla_idx ON public.tickets USING btree (status, assigned_to, super_users_notified_unassigned_sla_at);


--
-- Name: tickets_status_updated_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tickets_status_updated_idx ON public.tickets USING btree (status, updated_at);


--
-- Name: tickets_subject_trgm_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tickets_subject_trgm_idx ON public.tickets USING gin (lower((subject)::text) public.gin_trgm_ops);


--
-- Name: tickets_ticket_number_trgm_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tickets_ticket_number_trgm_idx ON public.tickets USING gin (lower((ticket_number)::text) public.gin_trgm_ops);


--
-- Name: tickets_type_status_created_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tickets_type_status_created_idx ON public.tickets USING btree (ticket_type, status, created_at);


--
-- Name: tickets_user_status_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tickets_user_status_idx ON public.tickets USING btree (user_id, status);


--
-- Name: tickets_user_updated_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tickets_user_updated_idx ON public.tickets USING btree (user_id, updated_at);


--
-- Name: user_legal_consents_user_accepted_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX user_legal_consents_user_accepted_idx ON public.user_legal_consents USING btree (user_id, accepted_at);


--
-- Name: users_email_trgm_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX users_email_trgm_idx ON public.users USING gin (lower((email)::text) public.gin_trgm_ops);


--
-- Name: users_name_trgm_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX users_name_trgm_idx ON public.users USING gin (lower((name)::text) public.gin_trgm_ops);


--
-- Name: users_role_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX users_role_is_active_index ON public.users USING btree (role, is_active);


--
-- Name: canned_responses canned_responses_created_by_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.canned_responses
    ADD CONSTRAINT canned_responses_created_by_user_id_foreign FOREIGN KEY (created_by_user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: credential_handoffs credential_handoffs_issued_by_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.credential_handoffs
    ADD CONSTRAINT credential_handoffs_issued_by_user_id_foreign FOREIGN KEY (issued_by_user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: credential_handoffs credential_handoffs_target_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.credential_handoffs
    ADD CONSTRAINT credential_handoffs_target_user_id_foreign FOREIGN KEY (target_user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: system_logs system_logs_actor_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.system_logs
    ADD CONSTRAINT system_logs_actor_user_id_foreign FOREIGN KEY (actor_user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: ticket_assignment_rules ticket_assignment_rules_category_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_assignment_rules
    ADD CONSTRAINT ticket_assignment_rules_category_id_foreign FOREIGN KEY (category_id) REFERENCES public.categories(id) ON DELETE CASCADE;


--
-- Name: ticket_assignment_rules ticket_assignment_rules_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_assignment_rules
    ADD CONSTRAINT ticket_assignment_rules_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: ticket_assignments ticket_assignments_ticket_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_assignments
    ADD CONSTRAINT ticket_assignments_ticket_id_foreign FOREIGN KEY (ticket_id) REFERENCES public.tickets(id) ON DELETE CASCADE;


--
-- Name: ticket_assignments ticket_assignments_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_assignments
    ADD CONSTRAINT ticket_assignments_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: ticket_replies ticket_replies_reply_to_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_replies
    ADD CONSTRAINT ticket_replies_reply_to_id_foreign FOREIGN KEY (reply_to_id) REFERENCES public.ticket_replies(id) ON DELETE SET NULL;


--
-- Name: ticket_replies ticket_replies_ticket_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_replies
    ADD CONSTRAINT ticket_replies_ticket_id_foreign FOREIGN KEY (ticket_id) REFERENCES public.tickets(id) ON DELETE CASCADE;


--
-- Name: ticket_replies ticket_replies_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_replies
    ADD CONSTRAINT ticket_replies_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: ticket_saved_views ticket_saved_views_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_saved_views
    ADD CONSTRAINT ticket_saved_views_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: ticket_user_states ticket_user_states_ticket_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_user_states
    ADD CONSTRAINT ticket_user_states_ticket_id_foreign FOREIGN KEY (ticket_id) REFERENCES public.tickets(id) ON DELETE CASCADE;


--
-- Name: ticket_user_states ticket_user_states_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_user_states
    ADD CONSTRAINT ticket_user_states_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: tickets tickets_assigned_to_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tickets
    ADD CONSTRAINT tickets_assigned_to_foreign FOREIGN KEY (assigned_to) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: tickets tickets_category_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tickets
    ADD CONSTRAINT tickets_category_id_foreign FOREIGN KEY (category_id) REFERENCES public.categories(id) ON DELETE CASCADE;


--
-- Name: tickets tickets_closed_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tickets
    ADD CONSTRAINT tickets_closed_by_foreign FOREIGN KEY (closed_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: tickets tickets_created_by_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tickets
    ADD CONSTRAINT tickets_created_by_user_id_foreign FOREIGN KEY (created_by_user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: tickets tickets_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tickets
    ADD CONSTRAINT tickets_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: user_legal_consents user_legal_consents_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_legal_consents
    ADD CONSTRAINT user_legal_consents_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- PostgreSQL database dump complete
--

\unrestrict bZmKeIY2UAPaFMREOxQJrcY0kxkzsAVbKgL4j71VPrxYR7DHD4SFXjB9Bah0WMk

--
-- PostgreSQL database dump
--

\restrict nhDRVwB0irumfqHXIzDF1GhVkDxQAGt2ItHcynvpOudpEdEbI96KdMVVACpcte5

-- Dumped from database version 17.11
-- Dumped by pg_dump version 17.11

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	2024_01_01_000001_create_users_table	1
2	2024_01_01_000002_create_categories_table	1
3	2024_01_01_000003_create_tickets_table	1
4	2024_01_01_000004_create_ticket_replies_table	1
5	2024_01_01_000005_create_attachments_table	1
6	2024_01_01_000006_create_sessions_table	1
7	2024_01_01_000007_create_cache_table	1
10	2026_02_18_000001_add_name_and_contact_number_to_tickets_table	1
11	2026_02_18_000002_add_hardware_and_software_categories	1
12	2026_02_18_000003_optimize_tickets_and_cleanup_replies	1
13	2026_02_19_000004_add_chat_state_fields_to_ticket_replies	1
14	2026_02_19_000005_drop_legacy_attachments_column_from_ticket_replies	1
16	2026_02_20_000007_add_email_province_municipality_to_tickets_table	1
19	2026_02_24_000010_backfill_legacy_ticket_profile_fields	1
20	2026_02_25_000011_create_ticket_user_states_table	1
21	2026_02_25_000012_add_ticket_email_alert_columns	1
22	2026_02_25_053350_create_failed_jobs_table	1
23	2026_02_25_053350_create_job_batches_table	1
24	2026_02_25_053350_create_jobs_table	1
27	2025_09_29_052531_update_users_table_add_super_admin_role	2
28	2025_09_29_061514_remove_agent_role_and_cleanup_users	2
29	2026_02_19_000006_add_technician_role_to_users_table	2
30	2026_02_20_000008_fix_sqlite_users_role_constraint	2
31	2026_02_23_000009_rename_support_roles	2
32	2026_02_25_140000_update_role_hierarchy_and_reset_default_passwords	2
33	2026_02_25_180000_create_user_legal_consents_table	2
34	2026_02_25_180100_add_ticket_consent_fields_to_tickets_table	2
35	2026_02_25_190000_create_system_logs_table	2
36	2026_02_25_200000_normalize_users_roles_to_shadow	2
37	2026_02_25_210000_move_attachments_to_private_storage	3
38	2026_02_26_000012_add_performance_indexes_for_tickets_and_replies	4
39	2026_02_26_000013_add_profile_edit_lock_to_users_table	4
40	2026_02_27_000014_add_password_reveal_to_users_table	4
41	2026_02_28_000015_create_credential_handoffs_table	5
42	2026_02_28_000016_drop_password_reveal_from_users_table	5
44	2026_03_03_000018_replace_shadow_notes_with_client_notes_on_users_table	6
45	2026_03_04_000019_add_search_indexes_for_ticket_lookup	7
46	2026_03_05_000020_add_must_change_password_to_users_table	7
47	2026_03_05_000021_add_unique_username_to_users_table	7
48	2026_03_26_000022_create_ticket_assignments_table	8
49	2026_03_26_000023_add_closed_by_to_tickets_table	9
50	2026_03_26_000024_drop_due_date_from_tickets_table	10
51	2026_03_27_120000_allow_null_ticket_priority	11
52	2026_03_30_000001_add_acknowledged_at_to_ticket_user_states_table	11
53	2026_04_07_150000_add_is_imported_to_tickets_table	12
54	2026_04_07_160000_convert_ticket_priority_values_to_severity_levels	12
55	2026_04_09_120000_add_ticket_type_to_tickets_table	12
56	2026_04_13_120000_prune_retired_ticket_categories	12
57	2026_04_13_120100_allow_nullable_email_for_users	12
58	2026_04_20_100000_create_departments_table	12
59	2026_04_22_000101_add_ticket_assignment_reminder_indexes	12
60	2026_04_23_000001_add_ticket_creation_context_to_tickets_table	12
61	2026_04_23_000002_backfill_ticket_creation_context_from_legacy_logs	12
62	2026_04_24_120000_add_admin_ticket_filter_indexes	12
63	2026_04_29_120000_add_reply_feed_updated_index	12
64	2026_05_25_000001_create_ticket_saved_views_table	13
65	2026_05_28_000001_add_normalized_name_to_ticket_saved_views_table	13
66	2026_06_23_000001_add_user_status_index_to_tickets_table	13
67	2026_06_24_000001_drop_unused_super_users_notified_columns	13
68	2026_06_24_000002_add_super_user_notification_columns_to_tickets	13
69	2026_06_24_095314_add_client_notified_reply_at_to_ticket_replies	13
70	2026_06_24_095315_drop_orphaned_canned_responses_table	13
71	2026_06_24_095316_add_role_is_active_index_to_users_table	13
72	2026_09_27_120000_create_ticket_assignment_rules_table	14
73	2026_09_27_120100_create_ticket_assignment_settings_table	14
74	2026_09_27_120200_create_canned_responses_table	14
\.


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.migrations_id_seq', 74, true);


--
-- PostgreSQL database dump complete
--

\unrestrict nhDRVwB0irumfqHXIzDF1GhVkDxQAGt2ItHcynvpOudpEdEbI96KdMVVACpcte5

